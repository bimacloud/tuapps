<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentRate;
use App\Models\Student;
use Exception;
use Illuminate\Support\Facades\DB;

class BillingService
{
    /**
     * Generate Invoices for a specific Class, Academic Year, Payment Type and Period.
     * Prevents duplication per student.
     */
    public function generateInvoices($academicYearId, $classId, $paymentTypeId, $period, $dueDate = null)
    {
        // Get the active rate for this payment type, year and class
        $rate = PaymentRate::where('payment_type_id', $paymentTypeId)
            ->where('academic_year_id', $academicYearId)
            ->where(function ($q) use ($classId) {
                $q->where('class_id', $classId)
                    ->orWhereNull('class_id'); // Fallback to global rate if no specific class rate
            })
            ->where('status', true)
            ->orderBy('class_id', 'desc') // Prioritize specific class rate over null
            ->first();

        if (! $rate) {
            throw new Exception('Tarif Pembayaran aktif tidak ditemukan untuk kelas ini.');
        }

        // Get all active students in the class
        $students = Student::where('class_id', $classId)
            ->where('status', 'AKTIF')
            ->get();

        if ($students->isEmpty()) {
            throw new Exception('Tidak ada siswa aktif di kelas ini.');
        }

        $generatedCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                // Check if this student already has this exact invoice item
                $exists = InvoiceItem::whereHas('invoice', function ($q) use ($student, $academicYearId) {
                    $q->where('student_id', $student->id)
                        ->where('academic_year_id', $academicYearId);
                })
                    ->where('payment_type_id', $paymentTypeId)
                    ->where('period', $period)
                    ->exists();

                if ($exists) {
                    $skippedCount++;

                    continue;
                }

                // Create a new Invoice
                // Based on plan: 1 invoice per generate action per student
                $invoice = Invoice::create([
                    'student_id' => $student->id,
                    'class_id' => $classId,
                    'academic_year_id' => $academicYearId,
                    'total_amount' => $rate->amount,
                    'paid_amount' => 0,
                    'remaining_amount' => $rate->amount,
                    'status' => 'BELUM BAYAR',
                    'due_date' => $dueDate,
                ]);

                // Create Invoice Item
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'payment_type_id' => $paymentTypeId,
                    'period' => $period,
                    'description' => 'Tagihan '.$rate->paymentType->name.' - '.$period,
                    'amount' => $rate->amount,
                    'paid_amount' => 0,
                    'remaining_amount' => $rate->amount,
                    'status' => 'BELUM BAYAR',
                ]);

                $generatedCount++;
            }
            DB::commit();

            return [
                'success' => true,
                'generated' => $generatedCount,
                'skipped' => $skippedCount,
                'message' => "Berhasil generate $generatedCount tagihan. Dilewati $skippedCount (sudah ada).",
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

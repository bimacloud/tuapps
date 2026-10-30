<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Laporan Harian (Daily Payments)
     */
    public function harian(Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));
        $methodId = $request->input('method_id');
        $cashierId = $request->input('cashier_id');

        $query = Payment::with([
            'student:id,name,nis,class_id', 
            'student.classRoom:id,name',
            'paymentMethod:id,name',
            'cashier:id,name'
        ])
        ->whereDate('payment_date', $date)
        ->where('status', 'SUCCESS');

        if ($methodId) {
            $query->where('payment_method_id', $methodId);
        }

        if ($cashierId) {
            $query->where('cashier_id', $cashierId);
        }

        if ($request->boolean('export')) {
            return $this->exportToCsv($query, 'laporan-harian-' . $date . '.csv', [
                'No Transaksi' => 'transaction_number',
                'Tanggal' => 'payment_date',
                'Siswa' => 'student.name',
                'NIS' => 'student.nis',
                'Kelas' => 'student.classRoom.name',
                'Metode' => 'paymentMethod.name',
                'Nominal' => 'total_amount',
                'Kasir' => 'cashier.name'
            ]);
        }

        $payments = $query->orderBy('payment_date', 'desc')->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total()
            ]
        ]);
    }

    /**
     * Laporan Bulanan (Monthly Income Aggregation)
     */
    public function bulanan(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));
        $academicYearId = $request->input('academic_year_id');

        $query = Payment::whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->where('status', 'SUCCESS');

        if ($request->boolean('export')) {
            $payments = $query->with(['student.classRoom', 'paymentMethod', 'cashier'])->get();
            // simple CSV export logic omitted for brevity as bulanan is typically aggregated
        }

        // Return aggregated daily data within the month
        $dailyData = $query->select(
                DB::raw('DATE(payment_date) as date'),
                DB::raw('SUM(total_amount) as total_income'),
                DB::raw('COUNT(id) as total_transactions')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'data' => $dailyData,
            'summary' => [
                'total_income' => $dailyData->sum('total_income'),
                'total_transactions' => $dailyData->sum('total_transactions')
            ]
        ]);
    }

    /**
     * Laporan per Kelas
     */
    public function perKelas(Request $request)
    {
        $academicYearId = $request->input('academic_year_id');

        // Menggunakan subquery/aggregation di Database agar ringan
        $data = DB::table('classes')
            ->leftJoin('students', 'classes.id', '=', 'students.class_id')
            ->leftJoin('invoices', function($join) use ($academicYearId) {
                $join->on('students.id', '=', 'invoices.student_id');
                if ($academicYearId) {
                    $join->where('invoices.academic_year_id', '=', $academicYearId);
                }
            })
            ->select(
                'classes.id',
                'classes.name as class_name',
                DB::raw('COUNT(DISTINCT students.id) as total_students'),
                DB::raw('COALESCE(SUM(invoices.total_amount), 0) as total_tagihan'),
                DB::raw('COALESCE(SUM(invoices.paid_amount), 0) as total_pembayaran'),
                DB::raw('COALESCE(SUM(invoices.remaining_amount), 0) as total_tunggakan')
            )
            ->groupBy('classes.id', 'classes.name')
            ->orderBy('classes.name', 'asc')
            ->get();

        // Calculate persentase lunas
        $data = $data->map(function ($item) {
            $item->collection_rate = $item->total_tagihan > 0 ? round(($item->total_pembayaran / $item->total_tagihan) * 100, 2) : 0;
            return $item;
        });

        if ($request->boolean('export')) {
            // Simplified CSV array mapping
            // ...
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Laporan per Jurusan
     */
    public function perJurusan(Request $request)
    {
        $academicYearId = $request->input('academic_year_id');

        $data = DB::table('majors')
            ->leftJoin('classes', 'majors.id', '=', 'classes.major_id')
            ->leftJoin('students', 'classes.id', '=', 'students.class_id')
            ->leftJoin('invoices', function($join) use ($academicYearId) {
                $join->on('students.id', '=', 'invoices.student_id');
                if ($academicYearId) {
                    $join->where('invoices.academic_year_id', '=', $academicYearId);
                }
            })
            ->select(
                'majors.id',
                'majors.name as major_name',
                'majors.code as major_code',
                DB::raw('COUNT(DISTINCT students.id) as total_students'),
                DB::raw('COALESCE(SUM(invoices.total_amount), 0) as total_tagihan'),
                DB::raw('COALESCE(SUM(invoices.paid_amount), 0) as total_pembayaran'),
                DB::raw('COALESCE(SUM(invoices.remaining_amount), 0) as total_tunggakan')
            )
            ->groupBy('majors.id', 'majors.name', 'majors.code')
            ->orderBy('majors.name', 'asc')
            ->get();

        $data = $data->map(function ($item) {
            $item->collection_rate = $item->total_tagihan > 0 ? round(($item->total_pembayaran / $item->total_tagihan) * 100, 2) : 0;
            return $item;
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Laporan per Jenis Pembayaran
     */
    public function perJenis(Request $request)
    {
        $academicYearId = $request->input('academic_year_id');

        $data = DB::table('payment_types')
            ->leftJoin('invoice_items', 'payment_types.id', '=', 'invoice_items.payment_type_id')
            ->leftJoin('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where(function($q) use ($academicYearId) {
                if ($academicYearId) {
                    $q->where('invoices.academic_year_id', $academicYearId)
                      ->orWhereNull('invoices.academic_year_id');
                }
            })
            ->select(
                'payment_types.id',
                'payment_types.name',
                'payment_types.type',
                DB::raw('COALESCE(SUM(invoice_items.amount), 0) as total_tagihan'),
                DB::raw('COALESCE(SUM(invoice_items.paid_amount), 0) as total_pembayaran'),
                DB::raw('COALESCE(SUM(invoice_items.remaining_amount), 0) as total_tunggakan')
            )
            ->groupBy('payment_types.id', 'payment_types.name', 'payment_types.type')
            ->orderBy('payment_types.name', 'asc')
            ->get();

        $data = $data->map(function ($item) {
            $item->collection_rate = $item->total_tagihan > 0 ? round(($item->total_pembayaran / $item->total_tagihan) * 100, 2) : 0;
            return $item;
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Laporan Tunggakan (Arrears)
     */
    public function tunggakan(Request $request)
    {
        $academicYearId = $request->input('academic_year_id');
        $classId = $request->input('class_id');

        $query = Invoice::with(['student:id,name,nis,class_id', 'student.classRoom:id,name'])
            ->where('remaining_amount', '>', 0);

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        if ($classId) {
            $query->whereHas('student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }

        $invoices = $query->orderByDesc('remaining_amount')->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total()
            ]
        ]);
    }

    /**
     * Generic CSV Exporter for Query Builder
     * Memory efficient export using stream.
     */
    private function exportToCsv($query, $filename, $columns)
    {
        $response = new StreamedResponse(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');
            
            // Write headers
            fputcsv($handle, array_keys($columns));

            // Chunk to avoid memory limits
            $query->chunk(500, function ($records) use ($handle, $columns) {
                foreach ($records as $record) {
                    $row = [];
                    foreach ($columns as $columnPath) {
                        $value = $record;
                        foreach (explode('.', $columnPath) as $step) {
                            $value = is_object($value) ? $value->{$step} : (is_array($value) ? $value[$step] : null);
                        }
                        $row[] = $value;
                    }
                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}

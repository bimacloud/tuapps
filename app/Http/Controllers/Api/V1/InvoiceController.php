<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Exception;

class InvoiceController extends Controller
{
    protected $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    public function index(Request $request)
    {
        $query = Invoice::with(['student.classRoom', 'student.major', 'academicYear', 'items.paymentType']);

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        if ($request->filled('class_id')) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                         ->orWhere('nis', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 10);
        return response()->json($invoices);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['student.classRoom', 'student.major', 'academicYear', 'items.paymentType']);
        return response()->json(['data' => $invoice]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'class_id' => 'required|exists:classes,id',
            'payment_type_id' => 'required|exists:payment_types,id',
            'period' => 'required|string|max:50',
            'due_date' => 'nullable|date'
        ]);

        try {
            $result = $this->billingService->generateInvoices(
                $validated['academic_year_id'],
                $validated['class_id'],
                $validated['payment_type_id'],
                $validated['period'],
                $validated['due_date'] ?? null
            );

            return response()->json($result);

        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}

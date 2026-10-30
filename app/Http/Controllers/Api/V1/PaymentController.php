<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Payment;
use App\Services\PaymentService;
use Exception;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function searchStudents(Request $request)
    {
        $query = $request->get('q');
        
        if (empty($query)) {
            return response()->json(['data' => []]);
        }

        $students = Student::with(['classRoom.major'])
            ->where('status', 'AKTIF')
            ->where(function ($q) use ($query) {
                $q->where('nis', 'like', "%{$query}%")
                  ->orWhere('nisn', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%");
            })
            // Only students who have unpaid invoices
            ->whereHas('invoices', function ($q) {
                $q->whereIn('status', ['BELUM BAYAR', 'SEBAGIAN']);
            })
            ->limit(10)
            ->get();

        return response()->json(['data' => $students]);
    }

    public function studentInvoices($studentId)
    {
        $invoices = Invoice::with(['academicYear', 'items' => function ($q) {
                $q->whereIn('status', ['BELUM BAYAR', 'SEBAGIAN'])
                  ->with('paymentType');
            }])
            ->where('student_id', $studentId)
            ->whereIn('status', ['BELUM BAYAR', 'SEBAGIAN'])
            ->get();

        return response()->json(['data' => $invoices]);
    }

    public function paymentMethods()
    {
        $methods = PaymentMethod::where('is_active', true)->get();
        return response()->json(['data' => $methods]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'items' => 'required|array|min:1',
            'items.*.invoice_item_id' => 'required|exists:invoice_items,id',
            'items.*.amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string'
        ]);

        try {
            $payment = $this->paymentService->processPayment($request->all());
            
            return response()->json([
                'message' => 'Pembayaran berhasil diproses',
                'data' => $payment
            ], 201);
            
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal memproses pembayaran: ' . $e->getMessage()
            ], 422);
        }
    }

    public function history(Request $request)
    {
        $query = Payment::with(['student.classRoom.major', 'paymentMethod', 'cashier'])
            ->orderBy('payment_date', 'desc');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                         ->orWhere('nis', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('date') && $request->date != '') {
            $query->whereDate('payment_date', $request->date);
        }

        if ($request->has('method') && $request->method != '') {
            $query->where('payment_method_id', $request->method);
        }

        $payments = $query->paginate(15);
        return response()->json($payments);
    }

    public function receipt($id)
    {
        $payment = Payment::with([
            'student.classRoom.major', 
            'paymentMethod', 
            'cashier',
            'items.invoiceItem.paymentType'
        ])->findOrFail($id);

        $settings = \App\Models\Setting::pluck('value', 'key');

        return response()->json([
            'data' => $payment,
            'settings' => $settings
        ]);
    }

    public function studentRecap($studentId)
    {
        // Administrasi Bulanan (July to June)
        // Administrasi Semester/Tahunan (Bebas)
        
        $invoices = Invoice::with(['academicYear', 'items.paymentType'])
            ->where('student_id', $studentId)
            ->get();
            
        $bulanan = [];
        $tahunan = [];
        
        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                if ($item->paymentType->type === 'BULANAN') {
                    $bulanan[] = $item;
                } else {
                    $tahunan[] = $item;
                }
            }
        }
        
        return response()->json([
            'data' => [
                'bulanan' => $bulanan,
                'tahunan' => $tahunan
            ]
        ]);
    }
}

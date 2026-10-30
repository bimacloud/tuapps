<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentRate;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentRateController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentRate::with(['paymentType', 'academicYear', 'classRoom.major']);
        
        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        $rates = $query->get();
        return response()->json(['data' => $rates]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_type_id' => 'required|exists:payment_types,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'class_id' => 'nullable|exists:classes,id',
            'amount' => 'required|numeric|min:1',
            'effective_date' => 'nullable|date',
            'status' => 'boolean'
        ]);

        if ($validated['status'] ?? true) {
            $duplicate = PaymentRate::where('payment_type_id', $validated['payment_type_id'])
                ->where('academic_year_id', $validated['academic_year_id'])
                ->where('class_id', $validated['class_id'] ?? null)
                ->where('status', true)
                ->exists();

            if ($duplicate) {
                return response()->json(['message' => 'Tarif aktif untuk Jenis Pembayaran, Tahun Ajaran, dan Kelas ini sudah ada.'], 422);
            }
        }

        $paymentRate = PaymentRate::create($validated);
        $paymentRate->load(['paymentType', 'academicYear', 'classRoom']);
        return response()->json(['data' => $paymentRate, 'message' => 'Tarif Pembayaran berhasil ditambahkan'], 201);
    }

    public function show(PaymentRate $paymentRate)
    {
        $paymentRate->load(['paymentType', 'academicYear', 'classRoom']);
        return response()->json(['data' => $paymentRate]);
    }

    public function update(Request $request, PaymentRate $paymentRate)
    {
        $validated = $request->validate([
            'payment_type_id' => 'required|exists:payment_types,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'class_id' => 'nullable|exists:classes,id',
            'amount' => 'required|numeric|min:1',
            'effective_date' => 'nullable|date',
            'status' => 'boolean'
        ]);

        if ($validated['status'] ?? true) {
            $duplicate = PaymentRate::where('payment_type_id', $validated['payment_type_id'])
                ->where('academic_year_id', $validated['academic_year_id'])
                ->where('class_id', $validated['class_id'] ?? null)
                ->where('status', true)
                ->where('id', '!=', $paymentRate->id)
                ->exists();

            if ($duplicate) {
                return response()->json(['message' => 'Tarif aktif untuk Jenis Pembayaran, Tahun Ajaran, dan Kelas ini sudah ada.'], 422);
            }
        }

        $paymentRate->update($validated);
        $paymentRate->load(['paymentType', 'academicYear', 'classRoom']);
        return response()->json(['data' => $paymentRate, 'message' => 'Tarif Pembayaran berhasil diupdate']);
    }

    public function destroy(PaymentRate $paymentRate)
    {
        // Actually, we shouldn't delete if there are invoice items connected.
        // Wait, we removed `payment_rate_id` from `invoice_items` in the new schema, so it's not strictly hard-linked,
        // but maybe we should allow deletion if no invoice items exist with the same amount/payment_type?
        // Let's just allow deletion since it's just a rate template.
        $paymentRate->delete();
        return response()->json(['message' => 'Tarif Pembayaran berhasil dihapus']);
    }
}

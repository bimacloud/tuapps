<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentType;
use Illuminate\Http\Request;

class PaymentTypeController extends Controller
{
    public function index()
    {
        $types = PaymentType::orderBy('name', 'asc')->get();
        return response()->json(['data' => $types]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:payment_types,code',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'type' => 'required|in:BULANAN,SEMESTER,TAHUNAN,KHUSUS',
            'description' => 'nullable|string',
            'status' => 'boolean'
        ]);

        $paymentType = PaymentType::create($validated);
        return response()->json(['data' => $paymentType, 'message' => 'Jenis Pembayaran berhasil ditambahkan'], 201);
    }

    public function show(PaymentType $paymentType)
    {
        return response()->json(['data' => $paymentType]);
    }

    public function update(Request $request, PaymentType $paymentType)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:payment_types,code,' . $paymentType->id,
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'type' => 'required|in:BULANAN,SEMESTER,TAHUNAN,KHUSUS',
            'description' => 'nullable|string',
            'status' => 'boolean'
        ]);

        $paymentType->update($validated);
        return response()->json(['data' => $paymentType, 'message' => 'Jenis Pembayaran berhasil diupdate']);
    }

    public function destroy(PaymentType $paymentType)
    {
        if ($paymentType->rates()->exists()) {
            return response()->json([
                'message' => 'Jenis Pembayaran tidak dapat dihapus karena sudah memiliki pengaturan tarif'
            ], 422);
        }

        $paymentType->delete();
        return response()->json(['message' => 'Jenis Pembayaran berhasil dihapus']);
    }
}

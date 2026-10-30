<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    public function index()
    {
        $guardians = Guardian::orderBy('name', 'asc')->get();
        return response()->json(['data' => $guardians]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'relation' => 'nullable|string|max:50',
        ]);

        $guardian = Guardian::create($validated);
        return response()->json(['data' => $guardian, 'message' => 'Orang Tua/Wali berhasil ditambahkan'], 201);
    }

    public function show(Guardian $guardian)
    {
        return response()->json(['data' => $guardian]);
    }

    public function update(Request $request, Guardian $guardian)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'relation' => 'nullable|string|max:50',
        ]);

        $guardian->update($validated);
        return response()->json(['data' => $guardian, 'message' => 'Orang Tua/Wali berhasil diupdate']);
    }

    public function destroy(Guardian $guardian)
    {
        if ($guardian->students()->exists()) {
            return response()->json([
                'message' => 'Data tidak dapat dihapus karena sudah terhubung ke data siswa'
            ], 422);
        }

        $guardian->delete();
        return response()->json(['message' => 'Orang Tua/Wali berhasil dihapus']);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class AcademicYearController extends Controller
{
    public function index()
    {
        $years = AcademicYear::orderBy('name', 'desc')->get();
        return response()->json(['data' => $years]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean'
        ]);

        DB::beginTransaction();
        try {
            if (!empty($validated['is_active'])) {
                AcademicYear::query()->update(['is_active' => false]);
            }

            $academicYear = AcademicYear::create($validated);
            DB::commit();

            return response()->json(['data' => $academicYear, 'message' => 'Tahun Pelajaran berhasil ditambahkan'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan data'], 500);
        }
    }

    public function show(AcademicYear $academicYear)
    {
        return response()->json(['data' => $academicYear]);
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean'
        ]);

        DB::beginTransaction();
        try {
            if (!empty($validated['is_active']) && !$academicYear->is_active) {
                AcademicYear::query()->update(['is_active' => false]);
            }

            $academicYear->update($validated);
            DB::commit();

            return response()->json(['data' => $academicYear, 'message' => 'Tahun Pelajaran berhasil diupdate']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengupdate data'], 500);
        }
    }

    public function destroy(AcademicYear $academicYear)
    {
        if ($academicYear->classes()->exists()) {
            return response()->json([
                'message' => 'Tahun Pelajaran tidak dapat dihapus karena sudah digunakan di data kelas'
            ], 422);
        }

        if ($academicYear->is_active) {
            return response()->json([
                'message' => 'Tahun Pelajaran yang sedang aktif tidak dapat dihapus'
            ], 422);
        }

        $academicYear->delete();
        return response()->json(['message' => 'Tahun Pelajaran berhasil dihapus']);
    }
}

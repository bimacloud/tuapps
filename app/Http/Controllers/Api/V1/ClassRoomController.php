<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use Illuminate\Http\Request;

class ClassRoomController extends Controller
{
    public function index()
    {
        $classes = ClassRoom::with(['major', 'academicYear'])
            ->orderBy('name', 'asc')
            ->get();
        return response()->json(['data' => $classes]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'major_id' => 'required|exists:majors,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'grade' => 'required|string|max:50',
            'homeroom_teacher' => 'nullable|string|max:255',
            'status' => 'boolean'
        ]);

        $classRoom = ClassRoom::create($validated);
        $classRoom->load(['major', 'academicYear']);

        return response()->json(['data' => $classRoom, 'message' => 'Kelas berhasil ditambahkan'], 201);
    }

    public function show(ClassRoom $classRoom)
    {
        $classRoom->load(['major', 'academicYear']);
        return response()->json(['data' => $classRoom]);
    }

    public function update(Request $request, ClassRoom $classRoom)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'major_id' => 'required|exists:majors,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'grade' => 'required|string|max:50',
            'homeroom_teacher' => 'nullable|string|max:255',
            'status' => 'boolean'
        ]);

        $classRoom->update($validated);
        $classRoom->load(['major', 'academicYear']);

        return response()->json(['data' => $classRoom, 'message' => 'Kelas berhasil diupdate']);
    }

    public function destroy(ClassRoom $classRoom)
    {
        if ($classRoom->students()->exists()) {
            return response()->json([
                'message' => 'Kelas tidak dapat dihapus karena sudah memiliki data siswa'
            ], 422);
        }

        $classRoom->delete();
        return response()->json(['message' => 'Kelas berhasil dihapus']);
    }
}

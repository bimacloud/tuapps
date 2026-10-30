<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Major;
use Illuminate\Http\Request;

class MajorController extends Controller
{
    public function index()
    {
        $majors = Major::orderBy('name', 'asc')->get();
        return response()->json(['data' => $majors]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:majors,code',
            'name' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        $major = Major::create($validated);
        return response()->json(['data' => $major, 'message' => 'Jurusan berhasil ditambahkan'], 201);
    }

    public function show(Major $major)
    {
        return response()->json(['data' => $major]);
    }

    public function update(Request $request, Major $major)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:majors,code,' . $major->id,
            'name' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        $major->update($validated);
        return response()->json(['data' => $major, 'message' => 'Jurusan berhasil diupdate']);
    }

    public function destroy(Major $major)
    {
        if ($major->classes()->exists()) {
            return response()->json([
                'message' => 'Jurusan tidak dapat dihapus karena sudah digunakan di data kelas'
            ], 422);
        }

        $major->delete();
        return response()->json(['message' => 'Jurusan berhasil dihapus']);
    }
}

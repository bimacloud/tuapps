<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\StudentExport;
use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::with(['classRoom.major', 'major', 'guardian']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nis', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('major_id')) {
            $query->where('major_id', $request->major_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sortField = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_dir', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->input('per_page', 10);
        $students = $query->paginate($perPage);

        return response()->json($students);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nis' => 'nullable|string|max:50|unique:students,nis',
            'nisn' => 'nullable|string|max:50|unique:students,nisn',
            'name' => 'required|string|max:255',
            'gender' => 'nullable|in:L,P',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'entry_year' => 'nullable|digits:4',
            'class_id' => 'required|exists:classes,id',
            'major_id' => 'nullable|exists:majors,id',
            'status' => 'required|in:AKTIF,LULUS,PINDAH,KELUAR',
            'photo' => 'nullable|image|max:2048',

            // Guardian fields
            'guardian_name' => 'nullable|string|max:255',
            'guardian_phone' => 'nullable|string|max:50',
            'guardian_relation' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();
        try {
            $guardianId = null;
            if (! empty($validated['guardian_name'])) {
                $guardian = Guardian::firstOrCreate(
                    ['name' => $validated['guardian_name'], 'phone' => $validated['guardian_phone'] ?? null],
                    ['relation' => $validated['guardian_relation'] ?? null]
                );
                $guardianId = $guardian->id;
            }

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('students', 'public');
            }

            $student = Student::create([
                'nis' => $validated['nis'] ?? null,
                'nisn' => $validated['nisn'] ?? null,
                'name' => $validated['name'],
                'gender' => $validated['gender'] ?? null,
                'birth_place' => $validated['birth_place'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'entry_year' => $validated['entry_year'] ?? null,
                'class_id' => $validated['class_id'],
                'major_id' => $validated['major_id'] ?? null,
                'status' => $validated['status'],
                'guardian_id' => $guardianId,
                'photo' => $photoPath,
            ]);

            DB::commit();

            $student->load(['classRoom.major', 'major', 'guardian']);

            return response()->json(['data' => $student, 'message' => 'Siswa berhasil ditambahkan'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }

            return response()->json(['message' => 'Gagal menyimpan data siswa: '.$e->getMessage()], 500);
        }
    }

    public function show(Student $student)
    {
        $student->load(['classRoom.major', 'major', 'guardian']);

        return response()->json(['data' => $student]);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'nis' => 'nullable|string|max:50|unique:students,nis,'.$student->id,
            'nisn' => 'nullable|string|max:50|unique:students,nisn,'.$student->id,
            'name' => 'required|string|max:255',
            'gender' => 'nullable|in:L,P',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'entry_year' => 'nullable|digits:4',
            'class_id' => 'required|exists:classes,id',
            'major_id' => 'nullable|exists:majors,id',
            'status' => 'required|in:AKTIF,LULUS,PINDAH,KELUAR',
            'photo' => 'nullable|image|max:2048',

            // Guardian fields
            'guardian_name' => 'nullable|string|max:255',
            'guardian_phone' => 'nullable|string|max:50',
            'guardian_relation' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();
        try {
            $guardianId = $student->guardian_id;
            if (! empty($validated['guardian_name'])) {
                $guardian = Guardian::firstOrCreate(
                    ['name' => $validated['guardian_name'], 'phone' => $validated['guardian_phone'] ?? null],
                    ['relation' => $validated['guardian_relation'] ?? null]
                );
                $guardianId = $guardian->id;
            }

            $photoPath = $student->photo;
            if ($request->hasFile('photo')) {
                if ($photoPath) {
                    Storage::disk('public')->delete($photoPath);
                }
                $photoPath = $request->file('photo')->store('students', 'public');
            }

            $student->update([
                'nis' => $validated['nis'] ?? null,
                'nisn' => $validated['nisn'] ?? null,
                'name' => $validated['name'],
                'gender' => $validated['gender'] ?? null,
                'birth_place' => $validated['birth_place'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'entry_year' => $validated['entry_year'] ?? null,
                'class_id' => $validated['class_id'],
                'major_id' => $validated['major_id'] ?? null,
                'status' => $validated['status'],
                'guardian_id' => $guardianId,
                'photo' => $photoPath,
            ]);

            DB::commit();

            $student->load(['classRoom.major', 'major', 'guardian']);

            return response()->json(['data' => $student, 'message' => 'Siswa berhasil diupdate']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal mengupdate data siswa'], 500);
        }
    }

    public function destroy(Student $student)
    {
        // Add check if student has invoices or payment rates
        // Currently the migration cascade on delete, but we shouldn't allow it if there are transactions.
        // I will check if there are invoices (we don't have Invoice model setup yet, but we can use DB facade)

        $hasInvoices = DB::table('invoices')->where('student_id', $student->id)->exists();
        if ($hasInvoices) {
            return response()->json([
                'message' => 'Siswa tidak dapat dihapus karena sudah memiliki data tagihan/transaksi',
            ], 422);
        }

        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }

        $student->delete();

        return response()->json(['message' => 'Siswa berhasil dihapus']);
    }

    public function export(Request $request)
    {
        return Excel::download(new StudentExport($request), 'students.xlsx');
    }
}

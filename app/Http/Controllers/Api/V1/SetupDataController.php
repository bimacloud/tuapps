<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\SetupDataExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Invoice;
use App\Models\Major;
use App\Models\PaymentRate;
use App\Models\PaymentType;
use App\Models\Setting;
use App\Models\Student;
use App\Services\SetupData\SetupDataImporter;
use App\Services\SetupData\SetupDataset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Initial setup: progress checklist plus Excel template, export, preview and import per dataset.
 */
class SetupDataController extends Controller
{
    public function __construct(private SetupDataImporter $importer) {}

    /**
     * Setup steps in order, with how much data each step already has.
     */
    public function status(): JsonResponse
    {
        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $schoolName = Setting::query()->where('key', 'school_name')->value('value');

        $steps = [
            ['key' => 'settings', 'done' => filled($schoolName), 'count' => null, 'detail' => $schoolName],
            ['key' => 'academic-years', 'done' => (bool) $activeYear, 'count' => AcademicYear::count(), 'detail' => $activeYear?->name],
            ['key' => 'majors', 'done' => Major::exists(), 'count' => Major::count(), 'detail' => null],
            [
                'key' => 'classes',
                'done' => $activeYear && ClassRoom::where('academic_year_id', $activeYear->id)->exists(),
                'count' => $activeYear ? ClassRoom::where('academic_year_id', $activeYear->id)->count() : 0,
                'detail' => null,
            ],
            ['key' => 'students', 'done' => Student::where('status', 'AKTIF')->exists(), 'count' => Student::where('status', 'AKTIF')->count(), 'detail' => null],
            ['key' => 'payment-types', 'done' => PaymentType::exists(), 'count' => PaymentType::count(), 'detail' => null],
            [
                'key' => 'payment-rates',
                'done' => $activeYear && PaymentRate::where('academic_year_id', $activeYear->id)->exists(),
                'count' => $activeYear ? PaymentRate::where('academic_year_id', $activeYear->id)->count() : 0,
                'detail' => null,
            ],
            [
                'key' => 'invoices',
                'done' => $activeYear && Invoice::where('academic_year_id', $activeYear->id)->exists(),
                'count' => $activeYear ? Invoice::where('academic_year_id', $activeYear->id)->count() : 0,
                'detail' => null,
            ],
        ];

        return response()->json(['data' => $steps]);
    }

    public function template(Request $request, string $dataset): BinaryFileResponse
    {
        $setupDataset = $this->authorizedDataset($request, $dataset);

        return Excel::download(
            new SetupDataExport($setupDataset, $setupDataset->exampleRows()),
            "template-{$setupDataset->key()}.xlsx",
        );
    }

    public function export(Request $request, string $dataset): BinaryFileResponse
    {
        $setupDataset = $this->authorizedDataset($request, $dataset);

        return Excel::download(
            new SetupDataExport($setupDataset, $setupDataset->exportRows()),
            "data-{$setupDataset->key()}.xlsx",
        );
    }

    public function preview(Request $request, string $dataset): JsonResponse
    {
        $setupDataset = $this->authorizedDataset($request, $dataset);
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        return response()->json($this->importer->preview($setupDataset, $request->file('file')));
    }

    public function import(Request $request, string $dataset): JsonResponse
    {
        $setupDataset = $this->authorizedDataset($request, $dataset);
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        $summary = $this->importer->import($setupDataset, $request->file('file'));

        return response()->json([
            'summary' => $summary,
            'message' => "{$setupDataset->title()}: {$summary['create']} baru, {$summary['update']} diperbarui, {$summary['invalid']} dilewati.",
        ]);
    }

    private function authorizedDataset(Request $request, string $key): SetupDataset
    {
        $dataset = $this->importer->find($key);

        abort_if($dataset === null, 404);
        abort_unless($request->user()->hasRole($dataset->role()), 403, "Data {$dataset->title()} hanya bisa diimpor oleh {$dataset->role()}.");

        return $dataset;
    }
}

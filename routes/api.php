<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CashierSessionController;
use App\Http\Controllers\Api\V1\ClassRoomController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MajorController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentRateController;
use App\Http\Controllers\Api\V1\PaymentTypeController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/school-profile', [SettingsController::class, 'profile']);

    // Payment Gateway Webhook
    Route::post('/payment-gateway/webhook', [WebhookController::class, 'handle']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);

        // Kepsek Only
        Route::middleware('role:Kepala Sekolah')->group(function () {
            // Approval Management
            Route::get('/approvals', [ApprovalController::class, 'index']);
            Route::post('/approvals/{id}/approve', [ApprovalController::class, 'approve']);
            Route::post('/approvals/{id}/reject', [ApprovalController::class, 'reject']);

            // Audit Logs
            Route::get('/audit-logs', [AuditLogController::class, 'index']);
        });

        // Master Data - View access for every staff role
        Route::middleware('role:Kepala Sekolah|Bendahara|Administrasi')->group(function () {
            Route::apiResource('academic-years', AcademicYearController::class)->only(['index', 'show']);
            Route::apiResource('majors', MajorController::class)->only(['index', 'show']);
            Route::apiResource('classes', ClassRoomController::class)->only(['index', 'show'])->parameters(['classes' => 'classRoom']);
            Route::apiResource('guardians', GuardianController::class)->only(['index', 'show']);
            Route::apiResource('students', StudentController::class)->only(['index', 'show'])->where(['student' => '[0-9]+']);
        });

        // School identity - Kepsek and Administrasi
        Route::middleware('role:Kepala Sekolah|Administrasi')->group(function () {
            Route::get('/settings', [SettingsController::class, 'index']);
            Route::post('/settings', [SettingsController::class, 'update']);
        });

        // Finance - View access for Kepsek and Bendahara
        Route::middleware('role:Kepala Sekolah|Bendahara')->group(function () {
            // Dashboard metrics
            Route::get('/dashboard/metrics-kepsek', [DashboardController::class, 'metricsKepsek']);
            Route::get('/dashboard/metrics-bendahara', [DashboardController::class, 'metricsBendahara']);
            Route::get('/dashboard/charts', [DashboardController::class, 'charts']);

            // Reports
            Route::get('/reports/harian', [ReportController::class, 'harian']);
            Route::get('/reports/bulanan', [ReportController::class, 'bulanan']);
            Route::get('/reports/per-kelas', [ReportController::class, 'perKelas']);
            Route::get('/reports/per-jurusan', [ReportController::class, 'perJurusan']);
            Route::get('/reports/per-jenis', [ReportController::class, 'perJenis']);
            Route::get('/reports/tunggakan', [ReportController::class, 'tunggakan']);

            Route::apiResource('payment-types', PaymentTypeController::class)->only(['index', 'show']);
            Route::apiResource('payment-rates', PaymentRateController::class)->only(['index', 'show']);

            // Payment History & Read Access
            Route::get('/payments', [PaymentController::class, 'history']);
            Route::get('/payments/{id}/receipt', [PaymentController::class, 'receipt']);
            Route::get('/payments/student/{id}/recap', [PaymentController::class, 'studentRecap']);
            Route::get('/payments/student/{student}/card', [PaymentController::class, 'studentCard']);
            Route::get('/payment-cards', [PaymentController::class, 'classCards']);
            Route::get('/cashier-sessions', [CashierSessionController::class, 'index']);
        });

        // Master Data - Write access for Administrasi only
        Route::middleware('role:Administrasi')->group(function () {
            Route::get('/dashboard/metrics-administrasi', [DashboardController::class, 'metricsAdministrasi']);

            Route::apiResource('academic-years', AcademicYearController::class)->except(['index', 'show']);
            Route::apiResource('majors', MajorController::class)->except(['index', 'show']);
            Route::apiResource('classes', ClassRoomController::class)->except(['index', 'show'])->parameters(['classes' => 'classRoom']);
            Route::apiResource('guardians', GuardianController::class)->except(['index', 'show']);

            Route::post('students/import-preview', [StudentController::class, 'previewImport']);
            Route::post('students/import', [StudentController::class, 'importData']);
            Route::get('students/export', [StudentController::class, 'export']);
            Route::apiResource('students', StudentController::class)->except(['index', 'show']);
        });

        // Finance - Write access for Bendahara only
        Route::middleware('role:Bendahara')->group(function () {
            Route::apiResource('payment-types', PaymentTypeController::class)->except(['index', 'show']);
            Route::apiResource('payment-rates', PaymentRateController::class)->except(['index', 'show']);

            Route::post('invoices/generate', [InvoiceController::class, 'generate']);
            Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show']);

            // Payment (Cashier process)
            Route::get('/payments/students/search', [PaymentController::class, 'searchStudents']);
            Route::get('/payments/student/{id}/invoices', [PaymentController::class, 'studentInvoices']);
            Route::get('/payment-methods', [PaymentController::class, 'paymentMethods']);
            Route::post('/payments', [PaymentController::class, 'store']);

            // Payment Cancellation Request
            Route::post('/payments/{id}/cancel-request', [ApprovalController::class, 'requestCancellation']);

            // Cashier Session
            Route::get('/cashier-sessions/current', [CashierSessionController::class, 'current']);
            Route::get('/cashier-sessions/summary', [CashierSessionController::class, 'summary']);
            Route::post('/cashier-sessions/open', [CashierSessionController::class, 'open']);
            Route::post('/cashier-sessions/close', [CashierSessionController::class, 'close']);
        });
    });
});

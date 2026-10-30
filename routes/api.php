<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\CashierSessionController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\WebhookController;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

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

        // Master Data - View access for both Kepsek and Bendahara
        Route::middleware('role:Kepala Sekolah|Bendahara')->group(function () {
            // Dashboard metrics
            Route::get('/dashboard/metrics-kepsek', [\App\Http\Controllers\Api\V1\DashboardController::class, 'metricsKepsek']);
            Route::get('/dashboard/metrics-bendahara', [\App\Http\Controllers\Api\V1\DashboardController::class, 'metricsBendahara']);
            Route::get('/dashboard/charts', [\App\Http\Controllers\Api\V1\DashboardController::class, 'charts']);

            // Reports
            Route::get('/reports/harian', [\App\Http\Controllers\Api\V1\ReportController::class, 'harian']);
            Route::get('/reports/bulanan', [\App\Http\Controllers\Api\V1\ReportController::class, 'bulanan']);
            Route::get('/reports/per-kelas', [\App\Http\Controllers\Api\V1\ReportController::class, 'perKelas']);
            Route::get('/reports/per-jurusan', [\App\Http\Controllers\Api\V1\ReportController::class, 'perJurusan']);
            Route::get('/reports/per-jenis', [\App\Http\Controllers\Api\V1\ReportController::class, 'perJenis']);
            Route::get('/reports/tunggakan', [\App\Http\Controllers\Api\V1\ReportController::class, 'tunggakan']);

            Route::apiResource('academic-years', \App\Http\Controllers\Api\V1\AcademicYearController::class)->only(['index', 'show']);
            Route::apiResource('majors', \App\Http\Controllers\Api\V1\MajorController::class)->only(['index', 'show']);
            Route::apiResource('classes', \App\Http\Controllers\Api\V1\ClassRoomController::class)->only(['index', 'show']);
            Route::apiResource('guardians', \App\Http\Controllers\Api\V1\GuardianController::class)->only(['index', 'show']);
            Route::apiResource('students', \App\Http\Controllers\Api\V1\StudentController::class)->only(['index', 'show']);
            
            Route::apiResource('payment-types', \App\Http\Controllers\Api\V1\PaymentTypeController::class)->only(['index', 'show']);
            Route::apiResource('payment-rates', \App\Http\Controllers\Api\V1\PaymentRateController::class)->only(['index', 'show']);
            
            // Payment History & Read Access
            Route::get('/payments', [PaymentController::class, 'history']);
            Route::get('/payments/{id}/receipt', [PaymentController::class, 'receipt']);
            Route::get('/payments/student/{id}/recap', [PaymentController::class, 'studentRecap']);
            // Settings
            Route::get('/settings', [\App\Http\Controllers\Api\V1\SettingsController::class, 'index']);
            Route::post('/settings', [\App\Http\Controllers\Api\V1\SettingsController::class, 'update']);
        });

        // Master Data - Write access for Bendahara only
        Route::middleware('role:Bendahara')->group(function () {
            Route::apiResource('academic-years', \App\Http\Controllers\Api\V1\AcademicYearController::class)->except(['index', 'show']);
            Route::apiResource('majors', \App\Http\Controllers\Api\V1\MajorController::class)->except(['index', 'show']);
            Route::apiResource('classes', \App\Http\Controllers\Api\V1\ClassRoomController::class)->except(['index', 'show']);
            Route::apiResource('guardians', \App\Http\Controllers\Api\V1\GuardianController::class)->except(['index', 'show']);
            
            Route::post('students/import-preview', [\App\Http\Controllers\Api\V1\StudentController::class, 'previewImport']);
            Route::post('students/import', [\App\Http\Controllers\Api\V1\StudentController::class, 'importData']);
            Route::get('students/export', [\App\Http\Controllers\Api\V1\StudentController::class, 'export']);
            Route::apiResource('students', \App\Http\Controllers\Api\V1\StudentController::class)->except(['index', 'show']);

            Route::apiResource('payment-types', \App\Http\Controllers\Api\V1\PaymentTypeController::class)->except(['index', 'show']);
            Route::apiResource('payment-rates', \App\Http\Controllers\Api\V1\PaymentRateController::class)->except(['index', 'show']);

            Route::post('invoices/generate', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'generate']);
            Route::apiResource('invoices', \App\Http\Controllers\Api\V1\InvoiceController::class)->only(['index', 'show']);
            
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

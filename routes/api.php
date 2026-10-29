<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\FinancialReportController;
use App\Http\Controllers\Api\FinancialEntryController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LeadActivityController;
use App\Http\Controllers\Api\SurveiController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\SettingController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('users', UserController::class);
    Route::put('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);

    Route::apiResource('roles', RoleController::class);
    Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions']);

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    Route::get('/transactions/stats', [App\Http\Controllers\Api\TransactionController::class, 'stats']);
    Route::apiResource('transactions', App\Http\Controllers\Api\TransactionController::class);

    // ── Financial Reports ──────────────────────────────────────────────────
    Route::prefix('reports')->group(function () {
        Route::get('/arus-kas',  [FinancialReportController::class, 'arusKas']);
        Route::get('/laba-rugi', [FinancialReportController::class, 'labaRugi']);
        Route::get('/neraca',    [FinancialReportController::class, 'neraca']);
    });

    // ── Financial Entries (Neraca manual items) ────────────────────────────
    Route::apiResource('financial-entries', FinancialEntryController::class);

    // ── Master Data ────────────────────────────────────────────────────────
    Route::get('parts/template', [\App\Http\Controllers\PartController::class, 'template']);
    Route::post('parts/import', [\App\Http\Controllers\PartController::class, 'import']);
    Route::apiResource('parts', \App\Http\Controllers\PartController::class);

    // ── Document Numbering ──────────────────────────────────────────────────
    Route::apiResource('document-numberings', \App\Http\Controllers\Api\DocumentNumberingController::class);

    // ── System / PDF Settings ──────────────────────────────────────────────
    Route::prefix('settings')->group(function () {
        Route::get('/penawaran-pdf', [SettingController::class, 'getPenawaranPdf']);
        Route::post('/penawaran-pdf', [SettingController::class, 'savePenawaranPdf']);
        Route::post('/penawaran-pdf/upload-image', [SettingController::class, 'uploadPenawaranPdfImage']);
        Route::delete('/penawaran-pdf/image', [SettingController::class, 'deletePenawaranPdfImage']);
    });

    // ── Database Client ──────────────────────────────────────────────────────────
    Route::apiResource('clients', \App\Http\Controllers\Api\ClientController::class);

    // ── CRM Leads ──────────────────────────────────────────────────────────
    Route::get('/leads/stats', [LeadController::class, 'stats']);
    Route::patch('/leads/{lead}/status', [LeadController::class, 'updateStatus']);
    Route::apiResource('leads', LeadController::class);

    // ── Lead Activities (Timeline SOP) ─────────────────────────────────────
    Route::get('/leads/{lead}/activities',         [LeadActivityController::class, 'index']);
    Route::post('/leads/{lead}/activities',        [LeadActivityController::class, 'store']);
    Route::delete('/leads/{lead}/activities/{activity}', [LeadActivityController::class, 'destroy']);

    // ── Form Survei ────────────────────────────────────────────────────────────
    Route::get('/surveis/stats', [SurveiController::class, 'stats']);
    Route::patch('/surveis/{survei}/status', [SurveiController::class, 'updateStatus']);
    Route::post('/surveis/{survei}/sync', [SurveiController::class, 'sync']);
    Route::apiResource('surveis', SurveiController::class);

    // ── Penawaran ──────────────────────────────────────────────────────────────
    Route::patch('/penawarans/{penawaran}/status', [\App\Http\Controllers\Api\PenawaranController::class, 'updateStatus']);
    Route::apiResource('penawarans', \App\Http\Controllers\Api\PenawaranController::class);

    // ── Proyek ─────────────────────────────────────────────────────────────────
    Route::get('/proyeks/stats', [\App\Http\Controllers\Api\ProyekController::class, 'stats']);
    Route::patch('/proyeks/{proyek}/status',   [\App\Http\Controllers\Api\ProyekController::class, 'updateStatus']);
    Route::patch('/proyeks/{proyek}/progress', [\App\Http\Controllers\Api\ProyekController::class, 'updateProgress']);
    Route::apiResource('proyeks', \App\Http\Controllers\Api\ProyekController::class);

    Route::prefix('proyeks/{proyek}')->group(function () {
        // Pembayaran Langsung & Status
        Route::post('/bayar-langsung',         [\App\Http\Controllers\Api\ProyekController::class, 'bayarLangsung']);
        Route::patch('/status-pembayaran',     [\App\Http\Controllers\Api\ProyekController::class, 'updateStatusPembayaran']);

        // Sewa
        Route::post('/perpanjang-sewa',        [\App\Http\Controllers\Api\ProyekController::class, 'perpanjangSewa']);
        Route::post('/generate-sewa-invoices', [\App\Http\Controllers\Api\ProyekController::class, 'generateSewaInvoices']);

        // Tim
        Route::get('/tim',          [\App\Http\Controllers\Api\ProyekTimController::class, 'index']);
        Route::post('/tim',         [\App\Http\Controllers\Api\ProyekTimController::class, 'store']);
        Route::delete('/tim/{tim}', [\App\Http\Controllers\Api\ProyekTimController::class, 'destroy']);

        // Log Pekerjaan
        Route::get('/log-pekerjaan',           [\App\Http\Controllers\Api\LogPekerjaanController::class, 'index']);
        Route::post('/log-pekerjaan',          [\App\Http\Controllers\Api\LogPekerjaanController::class, 'store']);
        Route::put('/log-pekerjaan/{log}',     [\App\Http\Controllers\Api\LogPekerjaanController::class, 'update']);
        Route::delete('/log-pekerjaan/{log}',  [\App\Http\Controllers\Api\LogPekerjaanController::class, 'destroy']);

        // QC
        Route::get('/qc',                  [\App\Http\Controllers\Api\ProyekQcController::class, 'show']);
        Route::put('/qc',                  [\App\Http\Controllers\Api\ProyekQcController::class, 'update']);
        Route::patch('/qc/items/{item}',   [\App\Http\Controllers\Api\ProyekQcController::class, 'updateItemCheck']);

        // Invoice
        Route::get('/invoices',                    [\App\Http\Controllers\Api\ProyekInvoiceController::class, 'index']);
        Route::post('/invoices',                   [\App\Http\Controllers\Api\ProyekInvoiceController::class, 'store']);
        Route::get('/invoices/{invoice}',          [\App\Http\Controllers\Api\ProyekInvoiceController::class, 'show']);
        Route::put('/invoices/{invoice}',          [\App\Http\Controllers\Api\ProyekInvoiceController::class, 'update']);
        Route::patch('/invoices/{invoice}/status', [\App\Http\Controllers\Api\ProyekInvoiceController::class, 'updateStatus']);
        Route::post('/invoices/{invoice}/bayar',   [\App\Http\Controllers\Api\ProyekInvoiceController::class, 'bayar']);
        Route::delete('/invoices/{invoice}',       [\App\Http\Controllers\Api\ProyekInvoiceController::class, 'destroy']);

        // Pembayaran & Jadwal Sewa
        Route::get('/pembayarans',                 [\App\Http\Controllers\Api\ProyekPembayaranController::class, 'index']);
        Route::post('/pembayarans',                [\App\Http\Controllers\Api\ProyekPembayaranController::class, 'store']);
        Route::put('/pembayarans/{pembayaran}',    [\App\Http\Controllers\Api\ProyekPembayaranController::class, 'update']);
        Route::delete('/pembayarans/{pembayaran}', [\App\Http\Controllers\Api\ProyekPembayaranController::class, 'destroy']);
        Route::get('/sewa-jadwal',                 [\App\Http\Controllers\Api\ProyekPembayaranController::class, 'sewaJadwal']);

        // Fee Tim
        Route::get('/fee-tim',                [\App\Http\Controllers\Api\ProyekFeeTimController::class, 'index']);
        Route::post('/fee-tim',               [\App\Http\Controllers\Api\ProyekFeeTimController::class, 'store']);
        Route::put('/fee-tim/{fee}',          [\App\Http\Controllers\Api\ProyekFeeTimController::class, 'update']);
        Route::post('/fee-tim/{fee}/riwayat', [\App\Http\Controllers\Api\ProyekFeeTimController::class, 'addRiwayat']);
        Route::delete('/fee-tim/{fee}',       [\App\Http\Controllers\Api\ProyekFeeTimController::class, 'destroy']);

        // Biaya Lain
        Route::get('/biaya-lain',                   [\App\Http\Controllers\Api\ProyekBiayaLainController::class, 'index']);
        Route::post('/biaya-lain',                  [\App\Http\Controllers\Api\ProyekBiayaLainController::class, 'store']);
        Route::put('/biaya-lain/{biaya}',           [\App\Http\Controllers\Api\ProyekBiayaLainController::class, 'update']);
        Route::patch('/biaya-lain/{biaya}/status',  [\App\Http\Controllers\Api\ProyekBiayaLainController::class, 'updateStatus']);
        Route::delete('/biaya-lain/{biaya}',        [\App\Http\Controllers\Api\ProyekBiayaLainController::class, 'destroy']);

        // Procurement
        Route::get('/procurement',                  [\App\Http\Controllers\Api\ProyekProcurementController::class, 'index']);
        Route::post('/procurement',                 [\App\Http\Controllers\Api\ProyekProcurementController::class, 'store']);
        Route::put('/procurement/{item}',           [\App\Http\Controllers\Api\ProyekProcurementController::class, 'update']);
        Route::patch('/procurement/{item}/status',  [\App\Http\Controllers\Api\ProyekProcurementController::class, 'updateStatus']);
        Route::delete('/procurement/{item}',        [\App\Http\Controllers\Api\ProyekProcurementController::class, 'destroy']);
        // Serah Terima
        Route::get('/serah-terima',                          [\App\Http\Controllers\Api\ProyekSerahTerimaController::class, 'show']);
        Route::put('/serah-terima',                          [\App\Http\Controllers\Api\ProyekSerahTerimaController::class, 'update']);
        Route::put('/serah-terima/checklists',               [\App\Http\Controllers\Api\ProyekSerahTerimaController::class, 'updateChecklists']);
        Route::put('/serah-terima/equipments',               [\App\Http\Controllers\Api\ProyekSerahTerimaController::class, 'updateEquipments']);
        Route::put('/serah-terima/punch-lists',              [\App\Http\Controllers\Api\ProyekSerahTerimaController::class, 'updatePunchLists']);
        Route::post('/serah-terima/documents',               [\App\Http\Controllers\Api\ProyekSerahTerimaController::class, 'uploadDocument']);
        Route::delete('/serah-terima/documents/{document}',  [\App\Http\Controllers\Api\ProyekSerahTerimaController::class, 'deleteDocument']);
        Route::post('/serah-terima/photos',                  [\App\Http\Controllers\Api\ProyekSerahTerimaController::class, 'uploadPhoto']);
        Route::delete('/serah-terima/photos/{photo}',        [\App\Http\Controllers\Api\ProyekSerahTerimaController::class, 'deletePhoto']);
        Route::post('/serah-terima/signatures',              [\App\Http\Controllers\Api\ProyekSerahTerimaController::class, 'saveSignatures']);
        Route::post('/serah-terima/complete',                [\App\Http\Controllers\Api\ProyekSerahTerimaController::class, 'complete']);
    });
});

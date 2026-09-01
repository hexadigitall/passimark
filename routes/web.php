<?php
use App\Http\Controllers\{PassimarkController, PassimarkAdminController};
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function(){
    Route::get('/', [PassimarkController::class,'dashboard'])->name('dashboard');
    Route::post('/passimark/session/{session}/start', [PassimarkController::class,'start'])->name('passimark.start');
    Route::post('/passimark/attempt/{attempt}/answer', [PassimarkController::class,'answer'])->name('passimark.answer');
    Route::post('/passimark/attempt/{attempt}/finish', [PassimarkController::class,'finish'])->name('passimark.finish');
    Route::post('/passimark/session/{session}/request-approval', [PassimarkController::class,'requestApproval'])->name('passimark.approval.request');
});

Route::middleware(['auth','role:instructor,admin'])->prefix('admin')->group(function(){
    Route::get('/passimark', [PassimarkAdminController::class,'index'])->name('admin.passimark');
    Route::post('/passimark/progress/{progress}/approve', [PassimarkAdminController::class,'approve'])->name('admin.approve');
    Route::post('/passimark/progress/{progress}/reject', [PassimarkAdminController::class,'reject'])->name('admin.reject');
    Route::post('/passimark/questions/import', [PassimarkAdminController::class,'importQuestions'])->name('admin.import');
});

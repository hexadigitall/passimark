<?php
use App\Http\Controllers\{PassimarkController, PassimarkAdminController, AuthController};
use Illuminate\Support\Facades\Route;

// Auth routes (guest only)
Route::middleware(['guest'])->group(function(){
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Logout route
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Authenticated routes
Route::middleware(['auth'])->group(function(){
    Route::get('/', [PassimarkController::class,'dashboard'])->name('dashboard');
    Route::get('/profile', [PassimarkController::class,'profile'])->name('profile');
    Route::get('/settings', [PassimarkController::class,'settings'])->name('settings');
    Route::patch('/settings', [PassimarkController::class,'updateSettings'])->name('settings.update');
    Route::get('/api/sessions', [PassimarkController::class,'sessionsApi'])->name('api.sessions');
    Route::get('/api/progress', [PassimarkController::class,'progressApi'])->name('api.progress');
    Route::get('/passimark/attempt/{attempt}', [PassimarkController::class,'exam'])->name('passimark.exam');
    Route::get('/passimark/attempt/{attempt}/result', [PassimarkController::class,'result'])->name('passimark.result');
    Route::post('/passimark/session/{session}/start', [PassimarkController::class,'start'])->name('passimark.start');
    Route::post('/passimark/attempt/{attempt}/answer', [PassimarkController::class,'answer'])->name('passimark.answer');
    Route::post('/passimark/attempt/{attempt}/finish', [PassimarkController::class,'finish'])->name('passimark.finish');
    Route::post('/passimark/session/{session}/request-approval', [PassimarkController::class,'requestApproval'])->name('passimark.approval.request');
    Route::get('/passimark/session/{session}/review', [PassimarkController::class,'review'])->name('passimark.review');
});

Route::middleware(['auth','role:instructor,admin'])->prefix('admin')->group(function(){
    Route::get('/passimark', [PassimarkAdminController::class,'index'])->name('admin.passimark');
    Route::post('/passimark/progress/{progress}/approve', [PassimarkAdminController::class,'approve'])->name('admin.approve');
    Route::post('/passimark/progress/{progress}/reject', [PassimarkAdminController::class,'reject'])->name('admin.reject');
    Route::post('/passimark/questions/import', [PassimarkAdminController::class,'importQuestions'])->name('admin.import');
    Route::get('/import', [PassimarkAdminController::class,'importPage'])->name('admin.import.page');
    Route::post('/sessions', [PassimarkAdminController::class,'storeSession'])->name('admin.sessions.store');
    Route::put('/sessions/{session}', [PassimarkAdminController::class,'updateSession'])->name('admin.sessions.update');
    Route::delete('/sessions/{session}', [PassimarkAdminController::class,'destroySession'])->name('admin.sessions.destroy');
    Route::post('/certification-tracks', [PassimarkAdminController::class,'storeCertificationTrack'])->name('admin.tracks.store');
    Route::put('/certification-tracks/{certificationTrack}', [PassimarkAdminController::class,'updateCertificationTrack'])->name('admin.tracks.update');
    Route::delete('/certification-tracks/{certificationTrack}', [PassimarkAdminController::class,'destroyCertificationTrack'])->name('admin.tracks.destroy');
    Route::post('/tags', [PassimarkAdminController::class,'storeTag'])->name('admin.tags.store');
    Route::put('/tags/{tag}', [PassimarkAdminController::class,'updateTag'])->name('admin.tags.update');
    Route::delete('/tags/{tag}', [PassimarkAdminController::class,'destroyTag'])->name('admin.tags.destroy');
    Route::post('/exams', [PassimarkAdminController::class,'storeExam'])->name('admin.exams.store');
    Route::put('/exams/{exam}', [PassimarkAdminController::class,'updateExam'])->name('admin.exams.update');
    Route::delete('/exams/{exam}', [PassimarkAdminController::class,'destroyExam'])->name('admin.exams.destroy');
    Route::post('/questions', [PassimarkAdminController::class,'storeQuestion'])->name('admin.questions.store');
    Route::put('/questions/{question}', [PassimarkAdminController::class,'updateQuestion'])->name('admin.questions.update');
    Route::delete('/questions/{question}', [PassimarkAdminController::class,'destroyQuestion'])->name('admin.questions.destroy');
});

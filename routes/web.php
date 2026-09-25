<?php
use App\Http\Controllers\{PassimarkController, PassimarkAdminController, PassimarkCatalogController, PassimarkFunnelController, CertificateController, AuthController};
use Illuminate\Support\Facades\Route;

// Public credential verification (QR target) — no auth so a scanned code resolves anywhere.
Route::get('/verify/{credentialId}', [CertificateController::class, 'verify'])->name('passimark.verify');

// Auth routes (guest only)
Route::middleware(['guest'])->group(function(){
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Logout route
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Entry route. Declared outside the auth group so a guest reaches the controller and is
// sent to the first-run ladder (rung 1, Lock); an authenticated user still gets the
// dashboard exactly as before.
Route::get('/', [PassimarkController::class,'dashboard'])->name('dashboard');

// Authenticated routes
Route::middleware(['auth'])->group(function(){
    Route::get('/catalog/search', [PassimarkCatalogController::class,'search'])->name('passimark.catalog.search');
    Route::get('/certs/{certKey}', [PassimarkCatalogController::class,'cert'])->name('passimark.cert');
    Route::get('/certs/{certKey}/{track:slug}', [PassimarkCatalogController::class,'bundle'])->name('passimark.bundle');
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
    Route::get('/certificate/{progress}', [CertificateController::class,'show'])->name('passimark.certificate');
});

// Funnel — pre-login ladder. Rung 1 (Lock) is what a guest lands on at '/', so the
// walkthrough is reachable before any account exists. ensureEnrolled() is a no-op for
// guests, so these rungs render identically signed-in or signed-out.
Route::prefix('passimark')->name('passimark.funnel.')->group(function(){
    Route::get('/lock', [PassimarkFunnelController::class,'lock'])->name('lock');
    Route::get('/splash', [PassimarkFunnelController::class,'splash'])->name('splash');
    Route::get('/intro', [PassimarkFunnelController::class,'intro'])->name('intro');
    Route::get('/auth', [PassimarkFunnelController::class,'auth'])->name('auth');
});

// Funnel — post-login rungs. These read/write the learner's own record, so they stay gated.
Route::middleware(['auth'])->prefix('passimark')->name('passimark.funnel.')->group(function(){
    Route::get('/focus', [PassimarkFunnelController::class,'focus'])->name('focus');
    Route::get('/permissions', [PassimarkFunnelController::class,'permissions'])->name('permissions');
});

Route::middleware(['auth','role:instructor,admin'])->prefix('admin')->group(function(){
    Route::get('/passimark', [PassimarkAdminController::class,'index'])->name('admin.passimark');
    Route::get('/reports/learners', [PassimarkAdminController::class,'reportLearners'])->name('admin.reports.learners');
    Route::get('/reports/attempts', [PassimarkAdminController::class,'reportAttempts'])->name('admin.reports.attempts');
    Route::get('/reports/sessions', [PassimarkAdminController::class,'reportSessions'])->name('admin.reports.sessions');
    Route::get('/reports/questions', [PassimarkAdminController::class,'reportQuestions'])->name('admin.reports.questions');
    Route::get('/reports/tracks', [PassimarkAdminController::class,'reportTracks'])->name('admin.reports.tracks');
    Route::get('/reports/approvals', [PassimarkAdminController::class,'reportApprovals'])->name('admin.reports.approvals');
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

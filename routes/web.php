<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Employee;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute BLJ — dikelompokkan per peran. Setiap tahap menambah rute di file ini.
|--------------------------------------------------------------------------
*/

// ---- Publik: verifikasi sertifikat dari QR (BR-35) ----
Route::get('/verifikasi/{token}', \App\Http\Controllers\CertificateVerifyController::class)
    ->middleware('throttle:60,1')->name('certificates.verify');

// ---- Tamu (belum login) ----
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:30,1');
    Route::get('/lupa-password', [ForgotPasswordController::class, 'request'])->name('password.request');
    Route::post('/lupa-password', [ForgotPasswordController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'update'])->name('password.update');
});

Route::middleware(['auth', 'account'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/ganti-password', [PasswordController::class, 'edit'])->name('password.change');
    Route::post('/ganti-password', [PasswordController::class, 'update'])->name('password.change.update');

    Route::get('/', HomeController::class)->name('home');
    Route::get('/profil', ProfileController::class)->name('profile');

    // Berkas hanya lewat URL bertanda tangan & berbatas waktu (Brief 9.2)
    Route::get('/berkas/{file}/{variant?}', [\App\Http\Controllers\FileController::class, 'show'])
        ->middleware('signed')->name('files.show');

    // ---- Karyawan ----
    Route::middleware('role:employee')->group(function () {
        // Tahap 6 — Alur belajar
        Route::get('/beranda', \App\Http\Controllers\Employee\HomeController::class)->name('employee.home');
        Route::get('/riwayat', [Employee\HistoryController::class, 'index'])->name('employee.history');
        Route::get('/belajar/{assignment}/sertifikat', [Employee\HistoryController::class, 'certificate'])->name('learn.certificate');
        Route::get('/belajar/{assignment}', [Employee\LearningController::class, 'program'])->name('learn.program');
        Route::get('/belajar/{assignment}/materi/{lesson}', [Employee\LearningController::class, 'lesson'])->name('learn.lesson');
        Route::post('/belajar/{assignment}/materi/{lesson}/progres', [Employee\LearningController::class, 'progress'])
            ->middleware('throttle:120,1')->name('learn.progress');
        Route::get('/belajar/{assignment}/tes/{kind}', [Employee\TestController::class, 'intro'])->whereIn('kind', ['PRE', 'POST'])->name('learn.test.intro');
        Route::post('/belajar/{assignment}/tes/{kind}', [Employee\TestController::class, 'start'])->whereIn('kind', ['PRE', 'POST'])->name('learn.test.start');
        Route::get('/belajar/{assignment}/percobaan/{attempt}', [Employee\TestController::class, 'take'])->name('learn.test.take');
        Route::post('/belajar/{assignment}/percobaan/{attempt}/jawab', [Employee\TestController::class, 'answer'])->middleware('throttle:300,1')->name('learn.test.answer');
        Route::post('/belajar/{assignment}/percobaan/{attempt}/kumpulkan', [Employee\TestController::class, 'submit'])->name('learn.test.submit');
        Route::get('/belajar/{assignment}/percobaan/{attempt}/hasil', [Employee\TestController::class, 'result'])->name('learn.test.result');
        Route::get('/belajar/{assignment}/survei', [Employee\SurveyController::class, 'show'])->name('learn.survey');
        Route::post('/belajar/{assignment}/survei', [Employee\SurveyController::class, 'store'])->name('learn.survey.store');
    });

    // ---- Admin L&D ----
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/akun', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('/akun/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset');
        Route::post('/akun/{user}/buka-kunci', [Admin\UserController::class, 'unlock'])->name('users.unlock');
        Route::post('/akun/{user}/peran', [Admin\UserController::class, 'updateRoles'])->name('users.roles');
        Route::get('/audit-log', [Admin\AuditLogController::class, 'index'])->name('audit.index');

        // Tahap 3 — Karyawan & master data
        Route::get('/karyawan', [Admin\EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/karyawan/ekspor', [Admin\EmployeeController::class, 'export'])->name('employees.export');
        Route::get('/karyawan/impor', [Admin\EmployeeController::class, 'importForm'])->name('employees.import');
        Route::get('/karyawan/impor/template', [Admin\EmployeeController::class, 'template'])->name('employees.template');
        Route::post('/karyawan/impor/pratinjau', [Admin\EmployeeController::class, 'preview'])->name('employees.preview');
        Route::post('/karyawan/impor/simpan', [Admin\EmployeeController::class, 'commit'])->name('employees.commit');
        Route::get('/karyawan/impor/akun/{token}', [Admin\EmployeeController::class, 'credentials'])->name('employees.credentials');
        Route::get('/karyawan/{employee}', [Admin\EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/karyawan/{employee}/edit', [Admin\EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/karyawan/{employee}', [Admin\EmployeeController::class, 'update'])->name('employees.update');

        Route::get('/master/{type?}', [Admin\MasterDataController::class, 'index'])->name('master.index');
        Route::post('/master/{type}', [Admin\MasterDataController::class, 'store'])->name('master.store');
        Route::put('/master/{type}/{id}', [Admin\MasterDataController::class, 'update'])->name('master.update');
        Route::post('/master/{type}/{id}/toggle', [Admin\MasterDataController::class, 'toggle'])->name('master.toggle');
        Route::delete('/master/{type}/{id}', [Admin\MasterDataController::class, 'destroy'])->name('master.destroy');

        // Tahap 4 — Program, konten, bank soal, survei
        Route::get('/program', [Admin\ProgramController::class, 'index'])->name('programs.index');
        Route::get('/program/baru', [Admin\ProgramController::class, 'create'])->name('programs.create');
        Route::post('/program', [Admin\ProgramController::class, 'store'])->name('programs.store');
        Route::get('/program/{program}', [Admin\ProgramController::class, 'show'])->name('programs.show');
        Route::get('/program/{program}/edit', [Admin\ProgramController::class, 'edit'])->name('programs.edit');
        Route::put('/program/{program}', [Admin\ProgramController::class, 'update'])->name('programs.update');
        Route::delete('/program/{program}', [Admin\ProgramController::class, 'destroy'])->name('programs.destroy');
        Route::post('/program/{program}/terbitkan', [Admin\ProgramController::class, 'publish'])->name('programs.publish');
        Route::post('/program/{program}/arsipkan', [Admin\ProgramController::class, 'archive'])->name('programs.archive');
        Route::post('/program/{program}/versi-baru', [Admin\ProgramController::class, 'newVersion'])->name('programs.version');
        Route::put('/program/{program}/tes', [Admin\ProgramController::class, 'updateAssessments'])->name('assessments.update');
        Route::get('/program/{program}/status-berkas', [Admin\ContentController::class, 'processingStatus'])->name('programs.processing');

        Route::post('/program/{program}/modul', [Admin\ContentController::class, 'storeModule'])->name('modules.store');
        Route::put('/modul/{module}', [Admin\ContentController::class, 'updateModule'])->name('modules.update');
        Route::delete('/modul/{module}', [Admin\ContentController::class, 'destroyModule'])->name('modules.destroy');
        Route::post('/modul/{module}/pindah/{dir}', [Admin\ContentController::class, 'moveModule'])->whereIn('dir', ['up', 'down'])->name('modules.move');
        Route::get('/modul/{module}/materi/baru', [Admin\ContentController::class, 'createLesson'])->name('lessons.create');
        Route::post('/modul/{module}/materi', [Admin\ContentController::class, 'storeLesson'])->name('lessons.store');
        Route::get('/materi/{lesson}/edit', [Admin\ContentController::class, 'editLesson'])->name('lessons.edit');
        Route::put('/materi/{lesson}', [Admin\ContentController::class, 'updateLesson'])->name('lessons.update');
        Route::delete('/materi/{lesson}', [Admin\ContentController::class, 'destroyLesson'])->name('lessons.destroy');
        Route::post('/materi/{lesson}/pindah/{dir}', [Admin\ContentController::class, 'moveLesson'])->whereIn('dir', ['up', 'down'])->name('lessons.move');
        Route::post('/materi/{lesson}/proses-ulang', [Admin\ContentController::class, 'retryProcessing'])->name('lessons.retry');

        Route::get('/program/{program}/soal', [Admin\QuestionController::class, 'index'])->name('questions.index');
        Route::get('/program/{program}/soal/baru', [Admin\QuestionController::class, 'create'])->name('questions.create');
        Route::post('/program/{program}/soal', [Admin\QuestionController::class, 'store'])->name('questions.store');
        Route::post('/program/{program}/soal/impor', [Admin\QuestionController::class, 'import'])->name('questions.import');
        Route::get('/soal/template', [Admin\QuestionController::class, 'template'])->name('questions.template');
        Route::get('/soal/{question}/edit', [Admin\QuestionController::class, 'edit'])->name('questions.edit');
        Route::put('/soal/{question}', [Admin\QuestionController::class, 'update'])->name('questions.update');
        Route::post('/soal/{question}/toggle', [Admin\QuestionController::class, 'toggle'])->name('questions.toggle');
        Route::delete('/soal/{question}', [Admin\QuestionController::class, 'destroy'])->name('questions.destroy');

        Route::get('/survei', [Admin\SurveyTemplateController::class, 'index'])->name('surveys.index');
        Route::get('/survei/baru', [Admin\SurveyTemplateController::class, 'edit'])->name('surveys.create');
        Route::post('/survei', [Admin\SurveyTemplateController::class, 'save'])->name('surveys.store');
        Route::get('/survei/{template}/edit', [Admin\SurveyTemplateController::class, 'edit'])->name('surveys.edit');
        Route::put('/survei/{template}', [Admin\SurveyTemplateController::class, 'save'])->name('surveys.update');

        // Tahap 5 — Aturan penugasan & penugasan
        Route::get('/aturan', [Admin\RuleController::class, 'index'])->name('rules.index');
        Route::get('/aturan/baru', [Admin\RuleController::class, 'create'])->name('rules.create');
        Route::post('/aturan/pratinjau', [Admin\RuleController::class, 'preview'])->name('rules.preview');
        Route::post('/aturan', [Admin\RuleController::class, 'store'])->name('rules.store');
        Route::get('/aturan/{rule}/edit', [Admin\RuleController::class, 'edit'])->name('rules.edit');
        Route::put('/aturan/{rule}', [Admin\RuleController::class, 'update'])->name('rules.update');
        Route::post('/aturan/{rule}/toggle', [Admin\RuleController::class, 'toggle'])->name('rules.toggle');

        Route::get('/penugasan', [Admin\AssignmentController::class, 'index'])->name('assignments.index');
        Route::get('/penugasan/ekspor', [Admin\AssignmentController::class, 'export'])->name('assignments.export');
        Route::get('/penugasan/manual', [Admin\AssignmentController::class, 'create'])->name('assignments.create');
        Route::post('/penugasan/manual', [Admin\AssignmentController::class, 'store'])->name('assignments.store');
        Route::get('/penugasan/cari-karyawan', [Admin\AssignmentController::class, 'searchEmployees'])->name('assignments.search');
        Route::get('/penugasan/{assignment}', [Admin\AssignmentController::class, 'show'])->name('assignments.show');
        Route::post('/penugasan/{assignment}/pengecualian', [Admin\AssignmentController::class, 'exempt'])->name('assignments.exempt');
    });
});

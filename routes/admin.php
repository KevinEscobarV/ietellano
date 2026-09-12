<?php

use App\Http\Controllers\Admin\BoletinDownloadController;
use App\Http\Controllers\Admin\CertificateDownloadController;
use App\Livewire\Admin\Attendance;
use App\Livewire\Admin\Boletines;
use App\Livewire\Admin\Certificates;
use App\Livewire\Admin\GradeEditor;
use App\Livewire\Admin\Grades;
use App\Livewire\Admin\Roles;
use App\Livewire\Admin\Structure;
use App\Livewire\Admin\Students;
use App\Livewire\Admin\Teachers;
use App\Livewire\Admin\TermClosure;
use App\Livewire\Admin\Users;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:super-admin|admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('roles', Roles::class)->name('roles');
    Route::livewire('users', Users::class)->name('users');
    Route::livewire('students', Students::class)->name('students');
    Route::livewire('teachers', Teachers::class)->name('teachers');
    Route::livewire('structure', Structure::class)->name('structure');
    Route::livewire('boletines', Boletines::class)->name('boletines');
    Route::get('boletines/download/{cycle}', [BoletinDownloadController::class, 'cycle'])->name('boletines.download');
    Route::get('boletines/download/{cycle}/{student}', [BoletinDownloadController::class, 'student'])->name('boletines.download-student');
    Route::livewire('certificates', Certificates::class)->name('certificates');
    Route::get('certificates/download/{cycle}', [CertificateDownloadController::class, 'cycle'])->name('certificates.download');
    Route::get('certificates/download/{cycle}/{student}', [CertificateDownloadController::class, 'student'])->name('certificates.download-student');
    Route::livewire('grades', Grades::class)->name('grades');
    Route::livewire('grade-editor', GradeEditor::class)->name('grade-editor');
    Route::livewire('attendance', Attendance::class)->name('attendance');
    Route::livewire('closure', TermClosure::class)->name('closure');
});

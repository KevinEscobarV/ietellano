<?php

use App\Livewire\Teacher\Gradebook;
use App\Livewire\Teacher\MyCourses;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'teacher'])->prefix('docente')->name('teacher.')->group(function () {
    Route::livewire('materias', MyCourses::class)->name('courses');
    Route::livewire('materias/{course}/notas', Gradebook::class)->name('gradebook');
});

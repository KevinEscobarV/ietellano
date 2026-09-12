<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();

        // Para un docente sin cargo administrativo, su tablero son sus materias.
        if ($user->isTeacherOnly()) {
            return redirect()->route('teacher.courses');
        }

        return view('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';

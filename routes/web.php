<?php

use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // El componente redirige al docente sin cargo administrativo a sus materias.
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');
});

require __DIR__.'/settings.php';

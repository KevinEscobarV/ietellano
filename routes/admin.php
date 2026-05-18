<?php

use App\Livewire\Admin\Roles;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:super-admin|admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('roles', Roles::class)->name('roles');
});

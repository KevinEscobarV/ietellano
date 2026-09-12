<?php

use App\Http\Controllers\Consulta\BoletinDownloadController;
use App\Livewire\Consulta\Boletin;
use App\Livewire\Consulta\Lookup;
use Illuminate\Support\Facades\Route;

/*
| Consulta pública de boletines. Es el único módulo sin autenticación: el
| estudiante entra con su documento y su apellido, ve sus notas y descarga el
| PDF. Los certificados no viven aquí, se siguen tramitando en la institución.
*/

Route::prefix('consulta')->name('consulta.')->group(function () {
    Route::livewire('/', Lookup::class)->name('lookup');
    Route::livewire('boletin', Boletin::class)->name('boletin');
    Route::get('boletin/{cycle}/pdf', BoletinDownloadController::class)->name('boletin.download');
});

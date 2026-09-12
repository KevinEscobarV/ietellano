<?php

namespace App\Livewire\Consulta;

use App\Services\StudentLookup;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * El ingreso del módulo público: documento y apellido, sin cuenta ni
 * contraseña. Comparte la pantalla de las demás puertas del sistema.
 */
#[Layout('layouts.auth')]
#[Title('Consulta de boletines')]
class Lookup extends Component
{
    /**
     * Intentos fallidos por IP antes de cerrar la consulta un rato. Sin este
     * tope, un número de cédula se encuentra probando; con él, no.
     */
    private const MAX_ATTEMPTS = 8;

    private const LOCKOUT_SECONDS = 300;

    public string $document = '';

    public string $lastName = '';

    public function consultar(StudentLookup $lookup): void
    {
        $this->validate([
            'document' => ['required', 'string', 'max:30'],
            'lastName' => ['required', 'string', 'max:120'],
        ]);

        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            $this->addError('document', __('Demasiados intentos. Espera :seconds segundos y vuelve a probar.', [
                'seconds' => RateLimiter::availableIn($this->throttleKey()),
            ]));

            return;
        }

        $student = $lookup->find($this->document, $this->lastName);

        if ($student === null) {
            RateLimiter::hit($this->throttleKey(), self::LOCKOUT_SECONDS);

            $this->addError('document', __('No encontramos un estudiante con ese documento y ese apellido. Revisa que estén bien escritos; si tu documento no está actualizado en la institución, acércate a secretaría.'));

            return;
        }

        RateLimiter::clear($this->throttleKey());

        session()->put(Boletin::SESSION_KEY, $student->id);
        session()->regenerate();

        $this->redirectRoute('consulta.boletin');
    }

    private function throttleKey(): string
    {
        return 'consulta-boletin:'.request()->ip();
    }

    public function render(): View
    {
        return view('livewire.consulta.lookup');
    }
}

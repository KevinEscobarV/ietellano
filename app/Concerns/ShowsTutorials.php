<?php

namespace App\Concerns;

/**
 * Para las pantallas de Livewire que traen un tutorial guiado (<x-tour>). La
 * pantalla lo abre sola mientras la persona no lo haya terminado o saltado, y
 * el botón "¿Cómo funciona?" lo vuelve a abrir cuando quiera.
 */
trait ShowsTutorials
{
    /**
     * Los nombres de tutorial que esta pantalla puede marcar como vistos. Es
     * una lista blanca: el nombre llega desde el navegador.
     *
     * @return list<string>
     */
    abstract protected function tutorials(): array;

    public function finishTutorial(string $name): void
    {
        abort_unless(in_array($name, $this->tutorials(), true), 404);

        auth()->user()?->markTutorialSeen($name);
    }

    protected function shouldStartTutorial(string $name): bool
    {
        $user = auth()->user();

        return $user !== null && ! $user->hasSeenTutorial($name);
    }
}

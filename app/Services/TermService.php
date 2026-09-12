<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\EditWindow;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Quién puede tocar todavía un semestre.
 *
 * La regla es corta: mientras el ciclo esté abierto, el docente edita; en
 * cuanto se cierra, deja de editar salvo que haya una ventana vigente sobre
 * su ciclo o sobre su materia.
 */
class TermService
{
    /**
     * Los ciclos cerrados y las ventanas vigentes se consultan una sola vez por
     * instancia. Quien pregunta muchas veces —la lista de materias del docente,
     * que lo hace por cada tarjeta— recibe el servicio inyectado y se queda con
     * una sola; el resto lo resuelve del contenedor y siempre lee lo último.
     *
     * @return Collection<int, CarbonInterface>
     */
    private function closedCycles(): Collection
    {
        return once(fn () => Cycle::whereNotNull('closed_at')->pluck('closed_at', 'id'));
    }

    /**
     * @return Collection<int, EditWindow>
     */
    private function openWindows(): Collection
    {
        return once(fn () => EditWindow::active()->get());
    }

    public function isClosed(Cycle|int|null $cycle): bool
    {
        $id = $cycle instanceof Cycle ? $cycle->id : $cycle;

        return $id !== null && $this->closedCycles()->has($id);
    }

    public function closedAt(Cycle|int|null $cycle): ?CarbonInterface
    {
        $id = $cycle instanceof Cycle ? $cycle->id : $cycle;

        return $id === null ? null : $this->closedCycles()->get($id);
    }

    /**
     * La ventana que cubre este curso, si hay alguna. Entre varias gana la que
     * termina más tarde, que es la que de verdad manda.
     */
    public function windowFor(Course $course): ?EditWindow
    {
        return $this->openWindows()
            ->filter(fn (EditWindow $window) => $window->cycle_id === $course->cycle_id
                && ($window->course_id === null || $window->course_id === $course->id))
            ->sortByDesc('closes_at')
            ->first();
    }

    /**
     * Si el docente puede escribir en este curso ahora mismo.
     */
    public function isOpen(Course $course): bool
    {
        return ! $this->isClosed($course->cycle_id) || $this->windowFor($course) !== null;
    }

    /**
     * Hasta cuándo dura el permiso, para decírselo al docente en pantalla. Es
     * null cuando el semestre sigue abierto y no hay plazo que avisar.
     */
    public function openUntil(Course $course): ?CarbonInterface
    {
        return $this->isClosed($course->cycle_id)
            ? $this->windowFor($course)?->closes_at
            : null;
    }
}

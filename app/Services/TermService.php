<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\EditWindow;
use App\Models\ModuleClosure;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Quién puede tocar todavía un módulo.
 *
 * La regla es corta: mientras el módulo de la materia esté abierto, el docente
 * edita; en cuanto se cierra, deja de editar salvo que haya una ventana
 * vigente sobre su ciclo, su módulo o su materia. El semestre está cerrado
 * cuando lo están sus dos módulos.
 */
class TermService
{
    /**
     * Los cierres y las ventanas vigentes se consultan una sola vez por
     * instancia. Quien pregunta muchas veces —la lista de materias del docente,
     * que lo hace por cada tarjeta— recibe el servicio inyectado y se queda con
     * una sola; el resto lo resuelve del contenedor y siempre lee lo último.
     *
     * La hora se compara al preguntar, no al consultar: un cierre programado
     * se cumple aunque la pantalla lleve rato abierta.
     *
     * @return Collection<string, ModuleClosure>
     */
    private function closures(): Collection
    {
        return once(fn () => ModuleClosure::all()->keyBy(fn (ModuleClosure $closure) => "{$closure->cycle_id}:{$closure->period}"));
    }

    /**
     * @return Collection<int, EditWindow>
     */
    private function openWindows(): Collection
    {
        return once(fn () => EditWindow::active()->get());
    }

    private function closure(int $cycleId, int $period): ?ModuleClosure
    {
        return $this->closures()->get("{$cycleId}:{$period}");
    }

    public function isModuleClosed(Cycle|int|null $cycle, int $period): bool
    {
        $id = $cycle instanceof Cycle ? $cycle->id : $cycle;

        return $id !== null && ($this->closure($id, $period)?->isClosed() ?? false);
    }

    /**
     * En qué está un módulo de un ciclo, para mostrarlo: abierto, con cierre
     * programado o cerrado, y desde o hasta cuándo.
     *
     * @return array{state: 'open'|'scheduled'|'closed', at: CarbonInterface|null}
     */
    public function moduleStatus(Cycle|int $cycle, int $period): array
    {
        $closure = $this->closure($cycle instanceof Cycle ? $cycle->id : $cycle, $period);

        return match (true) {
            $closure === null => ['state' => 'open', 'at' => null],
            $closure->isScheduled() => ['state' => 'scheduled', 'at' => $closure->closes_at],
            default => ['state' => 'closed', 'at' => $closure->closes_at],
        };
    }

    /**
     * El semestre entero: sus dos módulos cerrados.
     */
    public function isClosed(Cycle|int|null $cycle): bool
    {
        return collect(Cycle::MODULES)->every(fn (int $period) => $this->isModuleClosed($cycle, $period));
    }

    /**
     * Si el módulo de esta materia ya cerró, haya o no una ventana encima.
     */
    public function isCourseClosed(Course $course): bool
    {
        return $this->isModuleClosed($course->cycle_id, $course->period);
    }

    /**
     * La fecha en que se va a cerrar solo el módulo de esta materia, si tiene
     * un cierre programado.
     */
    public function scheduledClose(Course $course): ?CarbonInterface
    {
        $closure = $this->closure($course->cycle_id, $course->period);

        return $closure?->isScheduled() ? $closure->closes_at : null;
    }

    /**
     * La ventana que cubre este curso, si hay alguna. Entre varias gana la que
     * termina más tarde, que es la que de verdad manda.
     */
    public function windowFor(Course $course): ?EditWindow
    {
        return $this->openWindows()
            ->filter(fn (EditWindow $window) => $window->cycle_id === $course->cycle_id
                && ($window->course_id === null || $window->course_id === $course->id)
                && ($window->period === null || $window->period === $course->period))
            ->sortByDesc('closes_at')
            ->first();
    }

    /**
     * Si el docente puede escribir en este curso ahora mismo.
     */
    public function isOpen(Course $course): bool
    {
        return ! $this->isCourseClosed($course) || $this->windowFor($course) !== null;
    }

    /**
     * Hasta cuándo puede escribir el docente, para decírselo en pantalla: la
     * ventana si el módulo ya cerró, o el cierre programado si todavía no. Es
     * null cuando el módulo sigue abierto sin plazo.
     */
    public function openUntil(Course $course): ?CarbonInterface
    {
        $window = $this->windowFor($course)?->closes_at;

        if ($this->isCourseClosed($course)) {
            return $window;
        }

        $scheduled = $this->scheduledClose($course);

        if ($scheduled === null) {
            return null;
        }

        return $window !== null && $window->greaterThan($scheduled) ? $window : $scheduled;
    }
}

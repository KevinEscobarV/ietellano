<?php

namespace App\Livewire\Admin;

use App\Livewire\Teacher\Gradebook;
use App\Models\Course;

/**
 * La planilla de un curso vista por la coordinación. Es la misma del docente
 * —guardado al salir de la casilla, colores por desempeño, buscador—, pero la
 * coordinación entra a cualquier curso y escribe aunque el semestre esté
 * cerrado: es quien corrige lo que ya se entregó.
 */
class CourseGrades extends Gradebook
{
    protected function authorizeAccess(Course $course): void
    {
        $this->ensureCoordination();
    }

    protected function canWrite(): bool
    {
        return $this->isCoordination();
    }

    /**
     * La ruta ya exige el rol, pero las acciones de Livewire llegan por otra
     * ruta: se vuelve a comprobar en cada escritura.
     */
    protected function authorizeWrite(): void
    {
        $this->ensureCoordination();
    }

    /**
     * Vuelve al editor con el mismo ciclo elegido, que es de donde se vino.
     *
     * @return array{url: string, label: string}
     */
    protected function backLink(): array
    {
        $cycle = $this->course->cycle;

        return [
            'url' => route('admin.grade-editor', array_filter([
                'anio' => $cycle?->year,
                'semestre' => $cycle?->semester,
                'ciclo' => $cycle?->id,
            ])),
            'label' => __('Editar notas'),
        ];
    }

    protected function managedByCoordination(): bool
    {
        return true;
    }

    private function isCoordination(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super-admin', 'admin']);
    }

    private function ensureCoordination(): void
    {
        abort_unless($this->isCoordination(), 403);
    }
}

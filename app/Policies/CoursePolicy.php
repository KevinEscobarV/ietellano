<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\Services\TermService;

class CoursePolicy
{
    /**
     * Quién puede abrir un curso en el portal de docentes. La regla es una
     * sola: el curso tiene que estar asignado a su propio registro de docente.
     * Los administradores no pasan por aquí — usan el editor de notas del panel.
     */
    public function view(User $user, Course $course): bool
    {
        $teacherId = $user->teacher()->value('id');

        return $teacherId !== null && $course->teacher_id === $teacherId;
    }

    /**
     * Escribir notas es lo anterior más que el semestre siga abierto. Un
     * semestre cerrado se sigue viendo; lo que se pierde es la escritura, y
     * vuelve solo mientras haya una ventana de edición vigente.
     */
    public function grade(User $user, Course $course): bool
    {
        return $this->view($user, $course) && app(TermService::class)->isOpen($course);
    }

    /**
     * Pasar asistencia se rige por lo mismo: es su clase y el semestre está
     * abierto.
     */
    public function attend(User $user, Course $course): bool
    {
        return $this->grade($user, $course);
    }
}

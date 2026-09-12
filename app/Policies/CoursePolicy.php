<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Quién puede calificar un curso. La regla del portal de docentes es una
     * sola: el curso tiene que estar asignado a su propio registro de docente.
     * Los administradores no pasan por aquí — usan el editor de notas del panel.
     */
    public function grade(User $user, Course $course): bool
    {
        $teacherId = $user->teacher()->value('id');

        return $teacherId !== null && $course->teacher_id === $teacherId;
    }
}

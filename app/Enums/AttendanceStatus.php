<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'presente';
    case Absent = 'ausente';
    case Excused = 'justificada';

    public function label(): string
    {
        return match ($this) {
            self::Present => __('Presente'),
            self::Absent => __('Falló'),
            self::Excused => __('Justificada'),
        };
    }

    /**
     * Una falla justificada se registra, pero no cuenta para la pérdida de la
     * materia por inasistencia.
     */
    public function countsAgainst(): bool
    {
        return $this === self::Absent;
    }
}

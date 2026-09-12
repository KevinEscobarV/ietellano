<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El permiso temporal para editar un semestre ya cerrado. Sin curso vale para
 * todas las materias del ciclo; con curso, solo para esa.
 */
#[Fillable(['cycle_id', 'course_id', 'closes_at', 'note', 'opened_by'])]
class EditWindow extends Model
{
    protected function casts(): array
    {
        return ['closes_at' => 'datetime'];
    }

    /**
     * Las que todavía valen. Una ventana no se borra al vencerse: queda como
     * registro de que hubo una corrección autorizada.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('closes_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isFuture();
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
}

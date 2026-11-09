<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El permiso temporal para editar un módulo ya cerrado. Con curso vale solo
 * para esa materia; sin curso, para el módulo indicado o, sin módulo, para
 * todo el ciclo.
 */
#[Fillable(['cycle_id', 'course_id', 'period', 'closes_at', 'note', 'opened_by'])]
class EditWindow extends Model
{
    protected function casts(): array
    {
        return ['period' => 'integer', 'closes_at' => 'datetime'];
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

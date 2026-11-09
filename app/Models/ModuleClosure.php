<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El cierre de un módulo en un ciclo. Con la fecha ya pasada el módulo está
 * cerrado; con la fecha por venir está programado y se cierra solo a esa
 * hora. Sin registro, el módulo está abierto.
 */
#[Fillable(['cycle_id', 'period', 'closes_at', 'closed_by'])]
class ModuleClosure extends Model
{
    protected function casts(): array
    {
        return [
            'period' => 'integer',
            'closes_at' => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return ! $this->closes_at->isFuture();
    }

    public function isScheduled(): bool
    {
        return $this->closes_at->isFuture();
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un día de clase de un ciclo. Que exista la fila significa que ese día hubo
 * clase; un festivo se resuelve borrándola.
 */
#[Fillable(['cycle_id', 'date', 'note'])]
class ClassSession extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /**
     * La más reciente primero.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('date');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}

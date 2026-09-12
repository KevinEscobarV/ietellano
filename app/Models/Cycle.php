<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'level', 'semester', 'year', 'name', 'previous_cycle_id'])]
class Cycle extends Model
{
    /**
     * Cada nivel se repite semestre a semestre, así que el nombre por sí solo no
     * distingue el "Ciclo 5" de 2026-1 del de 2026-2.
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn () => "{$this->name} · {$this->year}-{$this->semester}");
    }

    /**
     * El semestre en curso primero.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('year')->orderByDesc('semester')->orderBy('level');
    }

    public function previousCycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class, 'previous_cycle_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'enrollments')->withPivot('group_id');
    }
}

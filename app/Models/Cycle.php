<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'level', 'semester', 'year', 'name', 'previous_cycle_id', 'starts_on', 'ends_on', 'class_weekday'])]
class Cycle extends Model
{
    /**
     * Todo ciclo se dicta en dos módulos, y cada materia es de uno de ellos
     * (`courses.period`).
     */
    public const MODULES = [1, 2];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

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

    /**
     * Un semestre cerrado ya no lo toca el docente: sus notas y su asistencia
     * quedan como quedaron, salvo que un administrador abra una ventana. Está
     * cerrado cuando lo están sus dos módulos.
     */
    public function isClosed(): bool
    {
        return collect(self::MODULES)->every(fn (int $period) => $this->isModuleClosed($period));
    }

    /**
     * Cuándo quedó cerrado el semestre: la fecha del último módulo que cerró.
     */
    public function closedAt(): ?CarbonInterface
    {
        return $this->isClosed() ? $this->moduleClosures->max('closes_at') : null;
    }

    public function closureFor(int $period): ?ModuleClosure
    {
        return $this->moduleClosures->firstWhere('period', $period);
    }

    public function isModuleClosed(int $period): bool
    {
        return $this->closureFor($period)?->isClosed() ?? false;
    }

    /**
     * Cierra el módulo ahora. Si ya estaba cerrado conserva la fecha en que se
     * cerró; si estaba programado, se adelanta.
     *
     * Las ventanas abiertas solo para este módulo o para sus materias se
     * vencen con él: quien cierra quiere que nadie siga escribiendo, y una
     * ventana abierta mientras el módulo corría no tiene nada que corregir.
     */
    public function closeModule(int $period, ?int $closedBy = null): void
    {
        if ($this->isModuleClosed($period)) {
            return;
        }

        $now = now();

        $this->moduleClosures()->updateOrCreate(['period' => $period], ['closes_at' => $now, 'closed_by' => $closedBy]);

        $this->editWindows()
            ->active()
            ->where(fn (Builder $query) => $query
                ->where('period', $period)
                ->orWhereHas('course', fn (Builder $course) => $course->where('period', $period)))
            ->update(['closes_at' => $now]);

        $this->unsetRelation('moduleClosures');
    }

    /**
     * Deja el módulo abierto hasta esa fecha y que se cierre solo a esa hora.
     */
    public function scheduleModuleClosure(int $period, CarbonInterface $closesAt, ?int $closedBy = null): void
    {
        $this->moduleClosures()->updateOrCreate(['period' => $period], ['closes_at' => $closesAt, 'closed_by' => $closedBy]);

        $this->unsetRelation('moduleClosures');
    }

    /**
     * Vuelve a abrir el módulo sin plazo, y le quita el cierre programado si
     * lo tenía.
     */
    public function reopenModule(int $period): void
    {
        $this->moduleClosures()->where('period', $period)->delete();

        $this->unsetRelation('moduleClosures');
    }

    /**
     * Cerrar los dos módulos es cerrar el semestre.
     */
    public function close(?int $closedBy = null): void
    {
        foreach (self::MODULES as $period) {
            $this->closeModule($period, $closedBy);
        }
    }

    public function reopen(): void
    {
        foreach (self::MODULES as $period) {
            $this->reopenModule($period);
        }
    }

    public function moduleClosures(): HasMany
    {
        return $this->hasMany(ModuleClosure::class);
    }

    public function editWindows(): HasMany
    {
        return $this->hasMany(EditWindow::class);
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

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
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

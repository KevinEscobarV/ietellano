<?php

namespace App\Models;

use App\Policies\CoursePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'cycle_id', 'subject_id', 'group_id', 'teacher_id', 'period'])]
#[UsePolicy(CoursePolicy::class)]
class Course extends Model
{
    protected function casts(): array
    {
        return [
            'period' => 'integer',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /**
     * Los estudiantes que le corresponden a este curso: los matriculados en su
     * ciclo y, cuando el curso está atado a un grupo, solo los de ese grupo.
     *
     * @return Builder<Student>
     */
    public function rosterQuery(): Builder
    {
        return Student::query()
            ->whereHas('enrollments', fn ($query) => $query
                ->where('cycle_id', $this->cycle_id)
                ->when($this->group_id, fn ($q) => $q->where('group_id', $this->group_id)))
            ->orderBy('last_name')
            ->orderBy('first_name');
    }
}

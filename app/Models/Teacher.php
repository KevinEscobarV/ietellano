<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['username', 'first_name', 'last_name', 'email', 'phone'])]
class Teacher extends Model
{
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * La cuenta con la que el docente entra al sistema, si un administrador ya
     * se la creó.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }
}

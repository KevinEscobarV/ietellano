<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
class Subject extends Model
{
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class);
    }
}

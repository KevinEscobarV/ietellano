<?php

use App\Livewire\Admin\Courses;
use App\Models\Cycle;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function ciclo(string $code, string $level, int $semester, int $year): Cycle
{
    return Cycle::create([
        'code' => $code,
        'level' => $level,
        'semester' => $semester,
        'year' => $year,
        'name' => "Ciclo {$level}",
    ]);
}

it('nombra el ciclo con su semestre, porque el nivel se repite cada semestre', function () {
    expect(ciclo('Ciclo5S22026', '5', 2, 2026)->label)->toBe('Ciclo 5 · 2026-2')
        ->and(ciclo('Ciclo5S12026', '5', 1, 2026)->label)->toBe('Ciclo 5 · 2026-1');
});

it('lista primero el semestre en curso', function () {
    ciclo('Ciclo4APrev2025', '4A', 2, 2025);
    ciclo('Ciclo6S12026', '6', 1, 2026);
    ciclo('Ciclo5S22026', '5', 2, 2026);
    ciclo('Ciclo3BS22026', '3B', 2, 2026);

    expect(Cycle::ordered()->get()->pluck('label')->all())->toBe([
        'Ciclo 3B · 2026-2',
        'Ciclo 5 · 2026-2',
        'Ciclo 6 · 2026-1',
        'Ciclo 4A · 2025-2',
    ]);
});

it('distingue los dos semestres en los selectores del panel', function () {
    Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    ciclo('Ciclo5S12026', '5', 1, 2026);
    ciclo('Ciclo5S22026', '5', 2, 2026);

    Livewire::actingAs($admin)->test(Courses::class)
        ->assertSeeInOrder(['Ciclo 5 · 2026-2', 'Ciclo 5 · 2026-1']);
});

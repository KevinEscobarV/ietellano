<?php

use App\Livewire\Admin\GradeEditor;
use App\Livewire\Teacher\MyCourses;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('docente');
});

test('el tutorial se abre solo la primera vez', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(GradeEditor::class)
        ->assertViewHas('startTour', true)
        ->call('finishTutorial', 'editor-notas');

    expect($admin->fresh()->hasSeenTutorial('editor-notas'))->toBeTrue();

    Livewire::actingAs($admin->fresh())
        ->test(GradeEditor::class)
        ->assertViewHas('startTour', false);
});

test('cada pantalla recuerda su propio tutorial', function () {
    $user = User::factory()->create();
    $user->assignRole('docente');
    Teacher::create(['username' => 'jperez', 'first_name' => 'Juan', 'last_name' => 'Perez'])->user()->associate($user)->save();

    $user->markTutorialSeen('planilla');

    Livewire::actingAs($user->fresh())
        ->test(MyCourses::class)
        ->assertViewHas('startTour', true);
});

test('marcarlo dos veces no lo duplica', function () {
    $user = User::factory()->create();

    $user->markTutorialSeen('planilla');
    $user->markTutorialSeen('planilla');

    expect($user->fresh()->tutorials_seen)->toBe(['planilla']);
});

test('una pantalla no marca tutoriales que no son suyos', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(GradeEditor::class)
        ->call('finishTutorial', 'cualquier-cosa')
        ->assertStatus(404);

    expect($admin->fresh()->tutorials_seen)->toBeNull();
});

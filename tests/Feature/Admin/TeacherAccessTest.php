<?php

use App\Livewire\Admin\Teachers;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('docente');
    Role::findOrCreate('admin');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->teacher = Teacher::create([
        'username' => 'jperez',
        'first_name' => 'Juan',
        'last_name' => 'Perez',
        'email' => 'jperez@iete.edu.co',
    ]);
});

test('el panel de docentes no es para cualquiera', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.teachers'))
        ->assertForbidden();
});

test('crear acceso deja al docente listo para entrar', function () {
    Livewire::actingAs($this->admin)
        ->test(Teachers::class)
        ->call('createAccess', $this->teacher->id)
        ->assertSet('showAccessModal', true)
        ->assertSet('accessEmail', 'jperez@iete.edu.co');

    $user = $this->teacher->fresh()->user;

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('jperez@iete.edu.co')
        ->and($user->hasRole('docente'))->toBeTrue()
        // Sin correo saliente, verificar por email dejaría la cuenta bloqueada.
        ->and($user->email_verified_at)->not->toBeNull();
});

test('la contraseña temporal se muestra una sola vez', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(Teachers::class)
        ->call('createAccess', $this->teacher->id);

    $password = $component->get('accessPassword');

    expect($password)->not->toBeEmpty()
        ->and(Hash::check($password, $this->teacher->fresh()->user->password))->toBeTrue();
});

test('un docente sin email no puede recibir acceso', function () {
    $this->teacher->update(['email' => null]);

    Livewire::actingAs($this->admin)
        ->test(Teachers::class)
        ->call('createAccess', $this->teacher->id)
        ->assertSet('showAccessModal', false);

    expect($this->teacher->fresh()->user_id)->toBeNull()
        ->and(User::where('email', 'jperez@iete.edu.co')->exists())->toBeFalse();
});

test('una cuenta que ya existía se vincula sin cambiarle la contraseña', function () {
    $existing = User::factory()->create(['email' => 'jperez@iete.edu.co']);
    $before = $existing->password;

    Livewire::actingAs($this->admin)
        ->test(Teachers::class)
        ->call('createAccess', $this->teacher->id)
        ->assertSet('accessPassword', '');

    expect($this->teacher->fresh()->user_id)->toBe($existing->id)
        ->and($existing->fresh()->password)->toBe($before)
        ->and($existing->fresh()->hasRole('docente'))->toBeTrue();
});

test('no se le puede dar la misma cuenta a dos docentes', function () {
    $otro = Teacher::create([
        'username' => 'mlopez',
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'email' => 'jperez@iete.edu.co',
    ]);

    Livewire::actingAs($this->admin)->test(Teachers::class)->call('createAccess', $this->teacher->id);

    Livewire::actingAs($this->admin)
        ->test(Teachers::class)
        ->call('createAccess', $otro->id)
        ->assertSet('showAccessModal', false);

    expect($otro->fresh()->user_id)->toBeNull();
});

test('generar una contraseña nueva reemplaza la anterior', function () {
    Livewire::actingAs($this->admin)->test(Teachers::class)->call('createAccess', $this->teacher->id);

    $before = $this->teacher->fresh()->user->password;

    $component = Livewire::actingAs($this->admin)
        ->test(Teachers::class)
        ->call('resetAccessPassword', $this->teacher->id);

    expect($this->teacher->fresh()->user->password)->not->toBe($before)
        ->and(Hash::check($component->get('accessPassword'), $this->teacher->fresh()->user->password))->toBeTrue();
});

test('quitar el acceso desvincula la cuenta y le retira el rol', function () {
    Livewire::actingAs($this->admin)->test(Teachers::class)->call('createAccess', $this->teacher->id);

    $user = $this->teacher->fresh()->user;

    Livewire::actingAs($this->admin)
        ->test(Teachers::class)
        ->call('revokeAccess', $this->teacher->id);

    expect($this->teacher->fresh()->user_id)->toBeNull()
        ->and($user->fresh()->hasRole('docente'))->toBeFalse()
        // La cuenta se conserva: borrarla es una decisión aparte, en Usuarios.
        ->and(User::whereKey($user->id)->exists())->toBeTrue();
});

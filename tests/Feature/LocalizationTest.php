<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

test('la aplicación corre en español', function () {
    expect(app()->getLocale())->toBe('es');
});

test('las páginas se declaran en español', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="es"', escape: false);
});

test('los errores de validación salen en español y nombran los campos como el usuario los ve', function () {
    $errors = Validator::make(
        ['first_name' => '', 'email' => 'esto-no-es-un-correo'],
        ['first_name' => ['required'], 'email' => ['email']],
    )->errors();

    expect($errors->first('first_name'))->toBe('El campo nombres es obligatorio.')
        ->and($errors->first('email'))->toBe('El campo correo electrónico no es un correo electrónico válido.');
});

test('la traducción cubre todas las reglas que define el framework', function () {
    // Si una versión nueva de Laravel agrega una regla, este test la delata
    // antes de que alguien se encuentre el mensaje en inglés en producción.
    $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
    $spanish = require lang_path('es/validation.php');

    // `custom` y `attributes` son de cada aplicación, no reglas del framework.
    $rules = fn (array $lines) => array_values(array_filter(
        array_keys(Arr::dot($lines)),
        fn (string $key) => ! str_starts_with($key, 'custom') && ! str_starts_with($key, 'attributes'),
    ));

    expect(array_diff($rules($english), $rules($spanish)))->toBe([]);
});

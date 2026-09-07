<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('public registration is closed', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse()
        ->and(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});

describe('when registration is enabled', function () {
    beforeEach(function () {
        $this->skipUnlessFortifyHas(Features::registration());
    });

    test('registration screen can be rendered', function () {
        $response = $this->get(route('register'));

        $response->assertOk();
    });

    test('new users can register', function () {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    });
});

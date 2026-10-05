<?php

declare(strict_types=1);

use App\Models\User;

test('un client peut se connecter avec les bons identifiants', function () {
    $user = User::factory()->create(['password' => bcrypt('mot-de-passe-sur')]);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'mot-de-passe-sur',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('la connexion échoue avec un mauvais mot de passe', function () {
    $user = User::factory()->create(['password' => bcrypt('mot-de-passe-sur')]);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'mauvais-mot-de-passe',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
});

test('la connexion est limitée à 5 tentatives par minute (email + IP)', function () {
    $user = User::factory()->create(['password' => bcrypt('mot-de-passe-sur')]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login'), ['email' => $user->email, 'password' => 'mauvais']);
    }

    $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'mauvais']);

    $response->assertStatus(429);
});

// Couverture "un client ne peut jamais accéder au BO" : voir tests/Feature/AdminPanelTest.php

<?php

declare(strict_types=1);

use App\Models\User;

test('la page de connexion du back-office est accessible et affiche le formulaire', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertSee('Connexion');
});

test('un client authentifié (guard web) ne peut pas accéder au back-office', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->get('/admin');

    $response->assertRedirect('/admin/login');
});

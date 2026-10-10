<?php

declare(strict_types=1);

use App\Models\Admin;
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

test('la page /admin/redirects n\'existe plus (404 pour un admin connecté, T27-L4)', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    // actingAs bascule le guard par défaut sur « admin » : on le remet sur « web »
    // pour que la page 404 publique (CartCountComposer) ne voie pas l'admin comme un client.
    auth()->shouldUse('web');

    $this->get('/admin/redirects')->assertNotFound();
});

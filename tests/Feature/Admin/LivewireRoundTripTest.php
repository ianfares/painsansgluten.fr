<?php

declare(strict_types=1);

use App\Models\Admin;

/*
 * Chaque composant Livewire d'une page d'admin doit accepter un aller-retour
 * serveur. Un composant inconnu de Livewire répond 419 en production
 * (« This page has expired »), comme les graphiques de « Recettes » le 09/10/2026.
 */
test('les composants Livewire des pages d\'admin répondent sans erreur 419', function (string $url) {
    config(['app.debug' => false]);
    $this->actingAs(Admin::factory()->create(), 'admin');

    $html = $this->get($url)->assertOk()->getContent();
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $snapshot) {
        $snapshot = html_entity_decode($snapshot, ENT_QUOTES);
        $name = json_decode($snapshot, true)['memo']['name'];

        $status = $this->postJson('/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]]])->status();

        expect($status)->toBe(200, "{$name} sur {$url}");
    }
})->with([
    'tableau de bord' => '/admin',
    'recettes' => '/admin/recettes',
    'commandes' => '/admin/orders',
    'produits' => '/admin/products',
    'demandes pro' => '/admin/pro-account-requests',
    'points de retrait' => '/admin/pickup-points',
]);

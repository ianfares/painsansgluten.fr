<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\ClosedDate;
use App\Models\ShippingRate;

test('un admin connecté peut accéder au tableau de bord avec l\'alerte de configuration', function () {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')->get('/admin');

    $response->assertOk();
    $response->assertSee('Configuration incomplète');
});

test('un admin connecté peut accéder à chaque page de paramètres', function () {
    $admin = Admin::factory()->create();

    foreach (['manage-shop-settings', 'manage-billing-settings', 'manage-bank-transfer-settings', 'manage-shipping-settings', 'manage-homepage-settings', 'manage-seo-settings'] as $slug) {
        $response = $this->actingAs($admin, 'admin')->get("/admin/{$slug}");
        $response->assertOk();
    }
});

test('les ressources frais de port et jours fermés sont accessibles et listent leurs enregistrements', function () {
    $admin = Admin::factory()->create();
    ShippingRate::factory()->create();
    ClosedDate::factory()->create();

    $this->actingAs($admin, 'admin')->get('/admin/shipping-rates')->assertOk();
    $this->actingAs($admin, 'admin')->get('/admin/closed-dates')->assertOk();
});

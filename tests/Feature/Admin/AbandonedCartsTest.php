<?php

declare(strict_types=1);

use App\Filament\Resources\AbandonedCartResource\Pages\ListAbandonedCarts;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutBlock;
use App\Models\Product;
use App\Models\User;
use App\Services\Analytics\AbandonedCartReport;
use App\Settings\ShippingSettings;
use Livewire\Livewire;

/**
 * Paniers abandonnés (27/10/2026) : paniers remplis, sans activité depuis une
 * heure, hors paniers techniques du BO ; nom seulement pour un client connecté.
 */
beforeEach(function () {
    $settings = app(ShippingSettings::class);
    $settings->minimum_order_chronopost_ttc = 1500;
    $settings->minimum_order_pickup_ttc = 1500;
    $settings->save();
});

function abandonedCart(int $priceTtc, int $quantity, ?User $user = null, string $lastActivity = '-2 hours', ?string $sessionId = null): Cart
{
    $cart = Cart::query()->create(['user_id' => $user?->id, 'session_id' => $user ? null : ($sessionId ?? 'sess-'.uniqid())]);
    $item = CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create(['price_ttc' => $priceTtc, 'name' => 'Pain '.$priceTtc])->id, 'quantity' => $quantity]);
    $item->forceFill(['updated_at' => now()->modify($lastActivity)])->saveQuietly();

    return $cart;
}

test('ne liste que les paniers remplis, inactifs depuis une heure, hors commandes saisies dans le BO', function () {
    $old = abandonedCart(500, 2);
    $recent = abandonedCart(500, 1, lastActivity: '-10 minutes');
    $admin = abandonedCart(500, 1, sessionId: 'admin-order-xyz');
    $empty = Cart::query()->create(['session_id' => 'vide']);

    $ids = app(AbandonedCartReport::class)->query()->pluck('id')->all();

    expect($ids)->toBe([$old->id])
        ->and($ids)->not->toContain($recent->id, $admin->id, $empty->id);
});

test('statistiques : part sous le minimum, montant moyen, refus suivis d\'une commande', function () {
    abandonedCart(500, 2);   // 10 € : sous le minimum
    abandonedCart(1000, 2);  // 20 €
    abandonedCart(1000, 1, lastActivity: '-20 days'); // hors 7 jours
    CheckoutBlock::query()->create(['reason' => 'minimum_order', 'delivery_method' => 'chronopost_relay', 'subtotal_ttc' => 1000, 'minimum_ttc' => 1500]);

    $stats = app(AbandonedCartReport::class)->stats(7);

    expect($stats)->toMatchArray(['abandoned' => 2, 'below_minimum' => 1, 'average_ttc' => 1500, 'blocks' => 1, 'blocks_then_ordered' => 0])
        ->and(app(AbandonedCartReport::class)->stats(30)['abandoned'])->toBe(3);
});

test('BO : la page affiche le nom du client connecté, « Visiteur » sinon, et le badge du minimum', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    abandonedCart(500, 2, User::factory()->create(['first_name' => 'Jeanne', 'last_name' => 'Dupont']));
    abandonedCart(2000, 1);

    Livewire::test(ListAbandonedCarts::class)
        ->assertOk()
        ->assertSee('Jeanne Dupont')
        ->assertSee('Visiteur non connecté')
        ->assertSee('2 × Pain 500')
        ->assertSee('10,00 €')
        ->assertSee('Sous le minimum')
        ->assertSee('Paniers abandonnés');
});

test('BO : le filtre « sous le minimum » ne garde que les petits paniers', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $small = abandonedCart(500, 2);
    $big = abandonedCart(2000, 1);

    Livewire::test(ListAbandonedCarts::class)
        ->filterTable('below_minimum')
        ->assertCanSeeTableRecords([$small])
        ->assertCanNotSeeTableRecords([$big]);
});

test('BO : la page n\'est pas accessible sans connexion admin', function () {
    $this->get('/admin/paniers-abandonnes')->assertRedirect();
});

test('BO : le menu propose le lien vers le rapport Google Analytics, ouvert dans un nouvel onglet', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    $this->get('/admin')->assertOk()
        ->assertSee('Rapport Google Analytics')
        ->assertSee('https://analytics.google.com/analytics/web/#/a62003154p558281421/reports/intelligenthome', false);
});

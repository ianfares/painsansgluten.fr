<?php

declare(strict_types=1);

use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Settings\ShippingSettings;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\URL;

test('un client voit le détail de sa commande : produits, relais, suivi et facture', function () {
    $shipping = app(ShippingSettings::class);
    $shipping->tracking_url_template = 'https://suivi.example/?lt={tracking}';
    $shipping->save();
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Shipped, 'relay_name' => 'Tabac de la Gare', 'tracking_number' => 'XY123']);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Pain de mie sans gluten']);
    $invoice = Invoice::factory()->create(['order_id' => $order->id, 'type' => InvoiceType::Invoice, 'number' => 'F2026-00077']);

    $this->actingAs($user)->get(route('compte.orders.show', $order->number))
        ->assertOk()
        ->assertSee('Pain de mie sans gluten')
        ->assertSee('Tabac de la Gare')
        ->assertSee('https://suivi.example/?lt=XY123', false)
        ->assertSee('F2026-00077')
        ->assertSee(route('invoices.download', $invoice), false);
});

test('la liste des commandes renvoie vers le détail', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('compte.orders'))->assertSee(route('compte.orders.show', $order->number), false);
});

test('impossible de voir le détail de la commande d\'un autre client (404)', function () {
    $other = Order::factory()->create(['user_id' => User::factory()->create()->id]);
    $guest = Order::factory()->create(['user_id' => null]);

    $this->actingAs(User::factory()->create())->get(route('compte.orders.show', $other->number))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('compte.orders.show', $guest->number))->assertNotFound();
});

test('les commandes passées en invité sont rattachées au compte une fois l\'email vérifié', function () {
    $user = User::factory()->unverified()->create(['email' => 'marie@example.test']);
    $guestOrder = Order::factory()->create(['user_id' => null, 'email' => 'marie@example.test']);
    $otherGuest = Order::factory()->create(['user_id' => null, 'email' => 'quelquun@example.test']);
    $otherClient = Order::factory()->create(['user_id' => User::factory()->create()->id, 'email' => 'marie@example.test']);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->actingAs($user)->get($url);

    expect($guestOrder->fresh()->user_id)->toBe($user->id)
        ->and($otherGuest->fresh()->user_id)->toBeNull()
        ->and($otherClient->fresh()->user_id)->not->toBe($user->id);
});

test('sans email vérifié, aucune commande invité n\'est rattachée', function () {
    $user = User::factory()->unverified()->create(['email' => 'marie@example.test']);
    $guestOrder = Order::factory()->create(['user_id' => null, 'email' => 'marie@example.test']);

    // Le client se connecte, navigue… mais n'a pas cliqué sur le lien de vérification.
    $this->actingAs($user)->get(route('home'));

    expect($guestOrder->fresh()->user_id)->toBeNull();
});

test('après un changement d\'email revérifié, les commandes invité de la nouvelle adresse sont rattachées', function () {
    $user = User::factory()->create(['email' => 'nouvelle@example.test']);
    $guestOrder = Order::factory()->create(['user_id' => null, 'email' => 'nouvelle@example.test']);

    event(new Verified($user));

    expect($guestOrder->fresh()->user_id)->toBe($user->id);
});

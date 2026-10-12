<?php

declare(strict_types=1);

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Documents\DeliveryNotePdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    Mail::fake();
});

function paidOrderWithItems(array $attributes = [], int $items = 2): Order
{
    $order = Order::factory()->paid()->create($attributes);
    OrderItem::factory()->count($items)->create(['order_id' => $order->id]);

    return $order->fresh();
}

test('le bon de livraison contient commande, client, produits et quantités, sans aucun prix', function () {
    $order = paidOrderWithItems(['delivery_method' => DeliveryMethod::ChronopostRelay]);

    $html = view('pdf.delivery-note', app(DeliveryNotePdf::class)->viewData([$order]))->render();

    expect($html)->toContain($order->number)
        ->toContain(e($order->last_name))
        ->toContain($order->relay_name)
        ->toContain('data:image/svg+xml;base64,')
        ->not->toContain('€')
        ->not->toContain('TVA');
    foreach ($order->items as $item) {
        expect($html)->toContain(e($item->product_name));
    }
});

test('le PDF est généré (en-tête %PDF) pour une ou plusieurs commandes', function () {
    $orders = [paidOrderWithItems(), paidOrderWithItems()];

    expect(app(DeliveryNotePdf::class)->render($orders))->toStartWith('%PDF');
});

test('le QR suit le format de la scanette (ancien modèle Shopify) : une seule ligne BL|CMD|DATE|CLIENT|ARTn|QTEn', function () {
    $order = paidOrderWithItems();
    $order->forceFill(['first_name' => 'Jeanne', 'last_name' => 'Du|Pont='])->save();
    $text = app(DeliveryNotePdf::class)->qrText($order->fresh());

    $expected = 'BL=BL-'.$order->number.'|CMD='.$order->number.'|DATE='.$order->created_at->timezone('Europe/Paris')->format('Y-m-d').'|CLIENT=Jeanne Du Pont';
    foreach ($order->items->values() as $i => $item) {
        $expected .= '|ART'.($i + 1).'='.$item->product_reference.'|QTE'.($i + 1).'='.$item->quantity;
    }

    expect($text)->toBe($expected)
        ->not->toContain("\n");
});

test('trop de produits : le QR garde l\'en-tête et des paires ART/QTE complètes, sans dépasser la limite', function () {
    $order = paidOrderWithItems([], 40);
    $text = app(DeliveryNotePdf::class)->qrText($order);

    expect(strlen($text))->toBeLessThanOrEqual(600)
        ->and($text)->toStartWith('BL=BL-'.$order->number.'|CMD='.$order->number)
        ->toMatch('/\|QTE\d+=\d+$/')
        ->and(substr_count($text, '|ART'))->toBe(substr_count($text, '|QTE'));
});

test('l\'action est visible pour une commande payée et masquée sinon', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $paid = paidOrderWithItems();
    $pending = Order::factory()->create(['status' => OrderStatus::PendingPayment]);
    $cancelled = Order::factory()->create(['status' => OrderStatus::Cancelled]);

    Livewire::test(ListOrders::class)
        ->assertTableActionVisible('deliveryNote', $paid)
        ->assertTableActionHidden('deliveryNote', $pending)
        ->assertTableActionHidden('deliveryNote', $cancelled);

    Livewire::test(ViewOrder::class, ['record' => $paid->getRouteKey()])->assertActionVisible('deliveryNote');
    Livewire::test(ViewOrder::class, ['record' => $pending->getRouteKey()])->assertActionHidden('deliveryNote');
});

test('l\'action télécharge un PDF', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $paid = paidOrderWithItems();

    Livewire::test(ListOrders::class)
        ->callTableAction('deliveryNote', $paid)
        ->assertFileDownloaded("bon-de-livraison-{$paid->number}.pdf");
});

test('l\'action de masse génère un seul PDF pour plusieurs commandes', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $orders = [paidOrderWithItems(), paidOrderWithItems()];
    $pending = Order::factory()->create(['status' => OrderStatus::PendingPayment]);

    Livewire::test(ListOrders::class)
        ->callTableBulkAction('deliveryNotes', [...$orders, $pending])
        ->assertFileDownloaded('bons-de-livraison.pdf');
});

test('un visiteur non connecté ne peut pas accéder aux commandes du BO', function () {
    $order = paidOrderWithItems();

    $this->get('/'.config('admin.path').'/orders/'.$order->id)->assertRedirect();
});

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

test('le texte du QR reprend commande, date, client, adresse et lignes', function () {
    $order = paidOrderWithItems(['delivery_method' => DeliveryMethod::MerchantPickup]);
    $text = app(DeliveryNotePdf::class)->qrText($order);

    expect($text)->toContain($order->number)
        ->toContain($order->created_at->format('d/m/Y'))
        ->toContain($order->last_name)
        ->toContain('3 avenue de la République')
        ->toContain(DeliveryMethod::MerchantPickup->label());
    foreach ($order->items as $item) {
        expect($text)->toContain($item->quantity.' x '.$item->product_name);
    }
});

test('le QR est tronqué quand il y a trop de produits, mais garde n° de commande et adresse', function () {
    $order = paidOrderWithItems([], 40);
    $text = app(DeliveryNotePdf::class)->qrText($order);

    expect(strlen($text))->toBeLessThanOrEqual(600)
        ->and($text)->toContain($order->number)
        ->toContain('3 avenue de la République')
        ->toEndWith('... (voir BL)');
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

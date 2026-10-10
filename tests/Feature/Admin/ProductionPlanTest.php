<?php

declare(strict_types=1);

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Filament\Pages\ProductionToDo;
use App\Filament\Widgets\ProductionStatsWidget;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Reporting\ProductionPlan;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function makeOrder(array $attrs, array $items): Order
{
    $order = Order::factory()->create($attrs + ['status' => OrderStatus::Paid, 'planned_ship_date' => today()->toDateString()]);
    foreach ($items as [$product, $quantity]) {
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product?->id,
            'product_name' => $product?->name ?? 'Produit supprimé',
            'quantity' => $quantity,
        ]);
    }

    return $order;
}

function plan(array $filters = []): ProductionPlan
{
    return ProductionPlan::fromFilters($filters + ['from' => today()->toDateString(), 'to' => today()->addDays(7)->toDateString()]);
}

beforeEach(function () {
    $this->pains = Category::factory()->create(['name' => 'Pains']);
    $this->biscuits = Category::factory()->create(['name' => 'Biscuits']);
    $this->pain = Product::factory()->create(['name' => 'Pain complet', 'category_id' => $this->pains->id]);
    $this->cookie = Product::factory()->create(['name' => 'Cookie', 'category_id' => $this->biscuits->id]);
});

test('les totaux cumulent les quantités par produit, triés par catégorie puis nom', function () {
    makeOrder([], [[$this->pain, 2], [$this->cookie, 1]]);
    makeOrder([], [[$this->pain, 3]]);

    $totals = plan()->totalsByProduct();

    expect($totals->all())->toBe([
        ['product_name' => 'Cookie', 'category' => 'Biscuits', 'quantity' => 1],
        ['product_name' => 'Pain complet', 'category' => 'Pains', 'quantity' => 5],
    ]);
});

test('seuls les statuts payé et en préparation sont comptés', function () {
    makeOrder(['status' => OrderStatus::Paid], [[$this->pain, 1]]);
    makeOrder(['status' => OrderStatus::Preparing], [[$this->pain, 1]]);
    foreach ([OrderStatus::ReadyForPickup, OrderStatus::Shipped, OrderStatus::PendingPayment, OrderStatus::Cancelled] as $status) {
        makeOrder(['status' => $status], [[$this->pain, 10]]);
    }

    expect(plan()->totalsByProduct()->sum('quantity'))->toBe(2);
});

test('le filtre de dates porte sur la date d\'expédition prévue, bornes incluses', function () {
    makeOrder(['planned_ship_date' => today()->subDay()->toDateString()], [[$this->pain, 100]]);
    makeOrder(['planned_ship_date' => today()->toDateString()], [[$this->pain, 1]]);
    makeOrder(['planned_ship_date' => today()->addDays(7)->toDateString()], [[$this->pain, 2]]);
    makeOrder(['planned_ship_date' => today()->addDays(8)->toDateString()], [[$this->pain, 100]]);

    expect(plan()->totalsByProduct()->sum('quantity'))->toBe(3);
});

test('filtres catégorie, produit et mode de livraison', function () {
    makeOrder(['delivery_method' => DeliveryMethod::ChronopostRelay], [[$this->pain, 2]]);
    makeOrder(['delivery_method' => DeliveryMethod::LabPickup], [[$this->cookie, 4]]);

    expect(plan(['categories' => [$this->pains->id]])->totalsByProduct()->sum('quantity'))->toBe(2)
        ->and(plan(['products' => [$this->cookie->id]])->totalsByProduct()->sum('quantity'))->toBe(4)
        ->and(plan(['delivery_methods' => [DeliveryMethod::LabPickup->value]])->totalsByProduct()->sum('quantity'))->toBe(4)
        ->and(plan(['delivery_methods' => [DeliveryMethod::MerchantPickup->value]])->totalsByProduct())->toBeEmpty();
});

test('un produit supprimé apparaît dans la catégorie Autre avec son nom d\'origine', function () {
    makeOrder([], [[null, 3]]);

    expect(plan()->totalsByProduct()->all())->toBe([
        ['product_name' => 'Produit supprimé', 'category' => 'Autre', 'quantity' => 3],
    ]);
});

test('le détail est triable par colonne et le tri inconnu retombe sur la date', function () {
    makeOrder(['number' => 'C2026-00002', 'planned_ship_date' => today()->addDay()->toDateString()], [[$this->pain, 1]]);
    makeOrder(['number' => 'C2026-00001', 'planned_ship_date' => today()->addDays(2)->toDateString()], [[$this->cookie, 5]]);

    expect(plan()->details()->pluck('order_number')->all())->toBe(['C2026-00002', 'C2026-00001'])
        ->and(plan()->details('order_number')->pluck('order_number')->all())->toBe(['C2026-00001', 'C2026-00002'])
        ->and(plan()->details('quantity', 'desc')->pluck('quantity')->all())->toBe([5, 1])
        ->and(plan()->details('n\'importe quoi')->pluck('order_number')->all())->toBe(['C2026-00002', 'C2026-00001']);
});

test('le détail ne fait pas de requête par ligne', function () {
    foreach (range(1, 5) as $i) {
        makeOrder([], [[$this->pain, 1], [$this->cookie, 1]]);
    }

    DB::enableQueryLog();
    plan()->details();
    plan()->totalsByProduct();

    expect(DB::getQueryLog())->toHaveCount(2);
});

test('l\'export PDF renvoie un fichier PDF à un admin', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    makeOrder([], [[$this->pain, 2]]);

    Livewire::test(ProductionToDo::class)
        ->call('exportPdf')
        ->assertFileDownloaded();
});

test('la page et le widget sont inaccessibles aux visiteurs et aux clients', function () {
    $this->get('/admin/a-produire')->assertRedirect();
    $this->actingAs(User::factory()->create())->get('/admin/a-produire')->assertRedirect();
});

test('la page affiche totaux et détail, et le widget compte aujourd\'hui et la semaine', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    makeOrder(['number' => 'C2026-00077'], [[$this->pain, 4]]);
    makeOrder(['planned_ship_date' => today()->addDays(3)->toDateString()], [[$this->pain, 6]]);

    Livewire::test(ProductionToDo::class)
        ->assertSee('Pain complet')
        ->assertSee('C2026-00077');

    Livewire::test(ProductionStatsWidget::class)
        ->assertSee('À produire aujourd\'hui')
        ->assertSee('À produire cette semaine');

    $widget = new ProductionPlan(CarbonImmutable::today(), CarbonImmutable::today()->addDays(6));
    expect($widget->totalsByProduct()->sum('quantity'))->toBe(10);
});

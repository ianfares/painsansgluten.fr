<?php

declare(strict_types=1);

use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Filament\Pages\Revenue;
use App\Filament\Revenue\Widgets\RevenueByCategoryChart;
use App\Filament\Revenue\Widgets\RevenueByPaymentMethodChart;
use App\Filament\Revenue\Widgets\RevenueStats;
use App\Filament\Revenue\Widgets\RevenueTimelineChart;
use App\Filament\Revenue\Widgets\TopProductsChart;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Reporting\RevenueReport;
use Carbon\CarbonImmutable;

/** Commande facturée : une ligne par [produit, montant TTC]. */
function invoicedOrder(array $lines, PaymentMethod $method, string $issuedAt, bool $refunded = false): Order
{
    $order = Order::factory()->create(['payment_method' => $method]);
    $total = 0;
    foreach ($lines as [$product, $amount]) {
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'line_total_ttc' => $amount, 'line_total_ht' => (int) round($amount / 1.055)]);
        $total += $amount;
    }

    $invoice = Invoice::factory()->create(['order_id' => $order->id, 'issued_at' => $issuedAt, 'total_ttc' => $total, 'total_ht' => (int) round($total / 1.055)]);

    if ($refunded) {
        Invoice::factory()->create(['order_id' => $order->id, 'type' => InvoiceType::CreditNote, 'issued_at' => $issuedAt, 'total_ttc' => -$total, 'total_ht' => -$invoice->total_ht, 'related_invoice_id' => $invoice->id]);
    }

    return $order;
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:00', 'Europe/Paris'));

    $pains = Category::factory()->create(['name' => 'Pains']);
    $biscuits = Category::factory()->create(['name' => 'Biscuits']);
    $this->nordique = Product::factory()->create(['name' => 'Pain Nordique', 'category_id' => $pains->id]);
    $this->cookie = Product::factory()->create(['name' => 'Cookie', 'category_id' => $biscuits->id]);

    invoicedOrder([[$this->nordique, 1000], [$this->cookie, 500]], PaymentMethod::Stripe, '2026-10-09 10:00');
    invoicedOrder([[$this->nordique, 2000]], PaymentMethod::BankTransfer, '2026-10-01 09:00');
    invoicedOrder([[$this->cookie, 700]], PaymentMethod::Stripe, '2026-10-08 11:00', refunded: true);
    invoicedOrder([[$this->cookie, 9999]], PaymentMethod::Stripe, '2026-05-01 10:00'); // hors 30 jours
    Order::factory()->create(['total_ttc' => 5000]); // non payée : aucune facture
});

test('recette = factures moins avoirs ; commandes remboursées et non payées exclues', function () {
    expect(RevenueReport::for('30d')->summary())->toMatchArray(['ttc' => 3500, 'orders' => 2, 'average_basket' => 1750])
        ->and(RevenueReport::for('today')->summary()['ttc'])->toBe(1500)
        ->and(RevenueReport::for('6m')->summary()['ttc'])->toBe(13499);
});

test('une période inconnue retombe sur 30 jours', function () {
    expect(RevenueReport::for('n\'importe quoi')->summary()['ttc'])->toBe(3500);
});

test('camemberts : par catégorie, par moyen de paiement et top produits', function () {
    $report = RevenueReport::for('30d');

    expect($report->byCategory())->toBe(['Pains' => 3000, 'Biscuits' => 500])
        ->and($report->byPaymentMethod())->toBe(['Virement bancaire' => 2000, 'Carte bancaire (Stripe)' => 1500])
        ->and($report->topProducts())->toBe(['Pain Nordique' => 3000, 'Cookie' => 500]);
});

test('la courbe découpe par jour sur 30 jours et par mois sur 12 mois', function () {
    $daily = RevenueReport::for('30d')->timeline();
    $monthly = RevenueReport::for('12m')->timeline();

    expect($daily)->toHaveCount(30)
        ->and($daily['09/10'])->toBe(1500)
        ->and($daily['08/10'])->toBe(0)
        ->and($monthly['10/2026'])->toBe(3500)
        ->and($monthly['05/2026'])->toBe(9999);
});

test('la page Recettes est réservée aux admins et s\'affiche avec ses graphiques', function () {
    $this->get('/admin/recettes')->assertRedirect();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/admin/recettes')
        ->assertOk()
        ->assertSee('Recettes')
        ->assertSee('Période');

    expect(Revenue::getNavigationGroup())->toBe('Comptabilité');
});

test('un client connecté n\'a pas accès à la page Recettes', function () {
    $this->actingAs(User::factory()->create())->get('/admin/recettes')->assertRedirect();
});

test('chaque graphique et les chiffres clés s\'affichent pour toutes les périodes', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    $widgets = [
        RevenueStats::class,
        RevenueTimelineChart::class,
        RevenueByCategoryChart::class,
        RevenueByPaymentMethodChart::class,
        TopProductsChart::class,
    ];

    foreach (array_keys(RevenueReport::PERIODS) as $period) {
        foreach ($widgets as $widget) {
            Livewire\Livewire::test($widget, ['filters' => ['period' => $period]])->assertOk();
        }
    }

    Livewire\Livewire::test(RevenueStats::class, ['filters' => ['period' => '30d']])
        ->assertSee('35,00 €')
        ->assertSee('17,50 €');
});

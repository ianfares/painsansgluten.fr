<?php

declare(strict_types=1);

use App\Actions\Invoicing\IssueCreditNoteAction;
use App\Actions\Invoicing\IssueInvoiceAction;
use App\Enums\InvoiceType;
use App\Events\Orders\OrderPaid;
use App\Listeners\Orders\GenerateInvoiceOnOrderPaid;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Settings\BillingSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');

    $billing = app(BillingSettings::class);
    $billing->company_name = 'Mon Sans Gluten by Angélique';
    $billing->siret = '12345678901234';
    $billing->save();
});

test('émet une facture avec un numéro séquentiel, un snapshot et un PDF', function () {
    $order = Order::factory()->create(['total_ht' => 1000, 'total_vat' => 55, 'total_ttc' => 1055]);
    OrderItem::factory()->for($order)->create(['vat_rate' => 5.5, 'line_total_ht' => 1000, 'line_total_ttc' => 1055]);

    $invoice = app(IssueInvoiceAction::class)->execute($order);

    expect($invoice->number)->toMatch('/^F\d{4}-\d{5}$/')
        ->and($invoice->type)->toBe(InvoiceType::Invoice)
        ->and($invoice->snapshot['seller']['name'])->toBe('Mon Sans Gluten by Angélique');

    Storage::disk('local')->assertExists($invoice->pdf_path);
});

test('les numéros de facture sont séquentiels sans trou', function () {
    $numbers = collect(range(1, 3))->map(function () {
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create();

        return (int) substr(app(IssueInvoiceAction::class)->execute($order)->number, -5);
    });

    expect($numbers->toArray())->toBe([$numbers[0], $numbers[0] + 1, $numbers[0] + 2]);
});

test('le snapshot de la facture reste inchangé si la commande est modifiée ensuite', function () {
    $order = Order::factory()->create(['billing_city' => 'Avranches']);
    OrderItem::factory()->for($order)->create(['product_name' => 'Le Mie\'miam']);

    $invoice = app(IssueInvoiceAction::class)->execute($order);
    $originalSnapshot = $invoice->snapshot;

    $order->update(['billing_city' => 'Paris']);
    $order->items->first()->update(['product_name' => 'Nom modifié']);

    expect($invoice->fresh()->snapshot)->toBe($originalSnapshot);
});

test('un avoir est lié à la facture d\'origine avec des montants négatifs', function () {
    $order = Order::factory()->create(['total_ht' => 1000, 'total_vat' => 55, 'total_ttc' => 1055]);
    OrderItem::factory()->for($order)->create();
    $invoice = app(IssueInvoiceAction::class)->execute($order);

    $creditNote = app(IssueCreditNoteAction::class)->execute($order);

    expect($creditNote->type)->toBe(InvoiceType::CreditNote)
        ->and($creditNote->related_invoice_id)->toBe($invoice->id)
        ->and($creditNote->total_ttc)->toBe(-$invoice->total_ttc)
        ->and($creditNote->number)->toMatch('/^A\d{4}-\d{5}$/');
});

test('le listener génère la facture au passage à paid, une seule fois', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create();

    (new GenerateInvoiceOnOrderPaid)->handle(new OrderPaid($order));
    (new GenerateInvoiceOnOrderPaid)->handle(new OrderPaid($order));

    expect($order->invoices()->count())->toBe(1);
});

test('un tiers ne peut pas télécharger la facture d\'un autre client', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $owner->id]);
    OrderItem::factory()->for($order)->create();
    $invoice = app(IssueInvoiceAction::class)->execute($order);

    $this->actingAs($intruder)->get(route('invoices.download', $invoice))->assertForbidden();
    $this->actingAs($owner)->get(route('invoices.download', $invoice))->assertOk();
});

test('un invité peut télécharger via une URL signée', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create();
    $invoice = app(IssueInvoiceAction::class)->execute($order);

    $url = URL::temporarySignedRoute('invoices.download', now()->addDays(30), ['invoice' => $invoice->id]);

    $this->get($url)->assertOk();
});

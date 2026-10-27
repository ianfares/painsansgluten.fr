<?php

declare(strict_types=1);

use App\Actions\Shipping\CancelShippingLabelAction;
use App\Actions\Shipping\GenerateShippingLabelAction;
use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Exceptions\Shipping\ShippingLabelFailed;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingLabel;
use App\Services\Chronopost\SoapClientFactory;
use App\Settings\ShippingSettings;
use App\Settings\ShopSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeChronopostSoapClientFactory;

/** Numéro de LT de la réponse officielle enregistrée (tests/Fixtures/chronopost/label-create-ok.xml). */
const FIXTURE_SKYBILL = 'XT341105180FR';

beforeEach(function () {
    Storage::fake('local');

    $shop = app(ShopSettings::class);
    $shop->shop_name = 'Mon Sans Gluten by Angélique';
    $shop->address_line1 = '1 rue du Laboratoire';
    $shop->postal_code = '50300';
    $shop->city = 'Avranches';
    $shop->contact_phone = '02 33 00 00 00';
    $shop->contact_email = 'boutique@example.com';
    $shop->save();

    $shipping = app(ShippingSettings::class);
    $shipping->chronopost_enabled = true;
    $shipping->save();

    $this->fake = new FakeChronopostSoapClientFactory(FakeChronopostSoapClientFactory::fixture('label-create-ok.xml'));
    $this->fake->responsesByMethod['cancelListSkybill'] = FakeChronopostSoapClientFactory::cancelResponse();
    app()->instance(SoapClientFactory::class, $this->fake);
});

/** Commande Chronopost payée, relais choisi dans la liste Chronopost (T11). */
function labelOrder(array $overrides = []): Order
{
    $order = Order::factory()->create(array_merge([
        'status' => OrderStatus::Preparing,
        'delivery_method' => DeliveryMethod::ChronopostRelay,
        'number' => 'C2026-00042',
        'email' => 'jeanne@example.com',
        'first_name' => 'Jeanne',
        'last_name' => 'Dupont',
        'phone' => '+33612345678',
        'relay_id' => '5755P',
        'relay_name' => 'LE LONGCHAMP',
        'relay_snapshot' => ['name' => 'LE LONGCHAMP', 'address_line1' => '55 RUE DE LA CONSTITUTION', 'postal_code' => '50300', 'city' => 'AVRANCHES', 'chronopost_id' => '5755P'],
    ], $overrides));
    OrderItem::factory()->for($order)->create(['weight_g' => 400, 'quantity' => 3]);

    return $order;
}

test('crée l\'étiquette chez Chronopost, stocke le PDF en privé et ne change pas le statut', function () {
    $order = labelOrder();

    $label = app(GenerateShippingLabelAction::class)->execute($order, null);

    expect($label->skybill_number)->toBe(FIXTURE_SKYBILL)
        ->and($label->weight_g)->toBe(1600) // 1 200 g de produits + 400 g d'emballage
        ->and($label->pdf_path)->toBe("shipping-labels/{$order->id}/".FIXTURE_SKYBILL.'.pdf')
        ->and(Storage::disk('local')->get($label->pdf_path))->toStartWith('%PDF')
        ->and($order->fresh()->status)->toBe(OrderStatus::Preparing)
        ->and($order->fresh()->tracking_number)->toBeNull();
});

test('envoie à Chronopost les données exigées pour un relais', function () {
    app(GenerateShippingLabelAction::class)->execute(labelOrder(), null);

    $request = $this->fake->requests[0];
    expect($request)
        ->toContain('<idRelais>5755P</idRelais>')
        ->toContain('<recipientName>LE LONGCHAMP</recipientName>')
        ->toContain('<recipientName2>Jeanne Dupont</recipientName2>')
        ->toContain('<recipientAdress1>55 RUE DE LA CONSTITUTION</recipientAdress1>')
        ->toContain('<recipientPhone>0612345678</recipientPhone>')
        ->toContain('<recipientEmail>jeanne@example.com</recipientEmail>')
        ->toContain('<productCode>86</productCode>')
        ->toContain('<service>0</service>')
        ->toContain('<weight>1.6</weight>')
        ->toContain('<mode>PDF</mode>')
        ->toContain('<insuredValue>0</insuredValue>')
        ->toContain('<shipperName>Mon Sans Gluten by Angelique</shipperName>')
        ->toContain('<shipperPhone>0233000000</shipperPhone>');
});

test('double clic : une seule étiquette, un seul appel à Chronopost', function () {
    $order = labelOrder();
    $action = app(GenerateShippingLabelAction::class);

    $first = $action->execute($order, null);
    $second = $action->execute($order, null);

    expect($second->id)->toBe($first->id)
        ->and($this->fake->requests)->toHaveCount(1)
        ->and(ShippingLabel::query()->count())->toBe(1);
});

test('refusée pour une commande non éligible, sans appeler Chronopost', function (array $overrides) {
    expect(fn () => app(GenerateShippingLabelAction::class)->execute(labelOrder($overrides), null))
        ->toThrow(ShippingLabelFailed::class);

    expect($this->fake->requests)->toBe([]);
})->with([
    'non payée' => [['status' => OrderStatus::PendingPayment]],
    'déjà expédiée' => [['status' => OrderStatus::Shipped]],
    'retrait chez un commerçant' => [['delivery_method' => DeliveryMethod::MerchantPickup]],
    'relais saisi à la main' => [['relay_id' => 'MANUEL-1761480000', 'relay_snapshot' => ['name' => 'Relais', 'postal_code' => '50300']]],
]);

test('refusée quand la livraison Chronopost est désactivée', function () {
    $shipping = app(ShippingSettings::class);
    $shipping->chronopost_enabled = false;
    $shipping->save();

    app(GenerateShippingLabelAction::class)->execute(labelOrder(), null);
})->throws(ShippingLabelFailed::class, 'désactivée');

test('refusée si les coordonnées de l\'expéditeur sont incomplètes', function () {
    $shop = app(ShopSettings::class);
    $shop->contact_phone = null;
    $shop->save();

    expect(fn () => app(GenerateShippingLabelAction::class)->execute(labelOrder(), null))
        ->toThrow(ShippingLabelFailed::class, 'téléphone');
    expect($this->fake->requests)->toBe([]);
});

test('un refus de Chronopost n\'enregistre ni étiquette ni fichier et affiche son message', function () {
    $this->fake->response = FakeChronopostSoapClientFactory::shippingErrorResponse(3, 'Le numéro de téléphone est invalide');

    expect(fn () => app(GenerateShippingLabelAction::class)->execute(labelOrder(), null))
        ->toThrow(ShippingLabelFailed::class, 'Le numéro de téléphone est invalide');

    expect(ShippingLabel::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('Chronopost injoignable : message clair, rien n\'est enregistré', function () {
    $this->fake->response = null;

    expect(fn () => app(GenerateShippingLabelAction::class)->execute(labelOrder(), null))
        ->toThrow(ShippingLabelFailed::class, 'injoignable');
    expect(ShippingLabel::query()->count())->toBe(0);
});

test('annule l\'étiquette chez Chronopost puis permet d\'en générer une nouvelle', function () {
    $order = labelOrder();
    $label = app(GenerateShippingLabelAction::class)->execute($order, null);

    app(CancelShippingLabelAction::class)->execute($order);

    expect($this->fake->requests[1])->toContain('cancelListSkybill')->toContain('<skybillNumber>'.FIXTURE_SKYBILL.'</skybillNumber>')
        ->and($label->fresh()->cancelled_at)->not->toBeNull()
        ->and($order->fresh()->activeShippingLabel)->toBeNull();

    // La réponse enregistrée renvoie toujours le même numéro de LT : on libère l'unicité pour le test.
    $label->update(['skybill_number' => 'ANNULEE-1']);
    $new = app(GenerateShippingLabelAction::class)->execute($order, null);
    expect($new->id)->not->toBe($label->id);
});

test('l\'annulation est refusée une fois le colis expédié', function () {
    $order = labelOrder();
    app(GenerateShippingLabelAction::class)->execute($order, null);
    $order->forceFill(['status' => OrderStatus::Shipped])->save();

    app(CancelShippingLabelAction::class)->execute($order);
})->throws(ShippingLabelFailed::class);

test('un refus d\'annulation de Chronopost laisse l\'étiquette active', function () {
    $order = labelOrder();
    $label = app(GenerateShippingLabelAction::class)->execute($order, null);
    $this->travel(20)->minutes();
    $this->fake->responsesByMethod['cancelListSkybill'] = FakeChronopostSoapClientFactory::cancelResponse(1, 'Colis déjà pris en charge');

    expect(fn () => app(CancelShippingLabelAction::class)->execute($order))->toThrow(ShippingLabelFailed::class, 'déjà pris en charge');
    expect($label->fresh()->cancelled_at)->toBeNull();
});

test('BO : l\'admin génère l\'étiquette, la télécharge, et « Expédier » est pré-rempli avec le numéro de suivi', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $order = labelOrder();

    Livewire::test(ListOrders::class)
        ->assertTableActionVisible('generateShippingLabel', $order)
        ->assertTableActionHidden('downloadShippingLabel', $order)
        ->callTableAction('generateShippingLabel', $order)
        ->assertHasNoTableActionErrors()
        ->assertFileDownloaded('etiquette-chronopost-C2026-00042.pdf');

    $order = $order->fresh(); // nouvelle requête HTTP dans le BO : commande relue
    Livewire::test(ListOrders::class)
        ->assertTableActionHidden('generateShippingLabel', $order)
        ->assertTableActionVisible('downloadShippingLabel', $order)
        ->assertTableActionVisible('cancelShippingLabel', $order)
        ->mountTableAction('ship', $order)
        ->assertTableActionDataSet(['tracking_number' => FIXTURE_SKYBILL]);
});

test('BO : les actions d\'étiquette ne sont pas accessibles sans connexion admin', function () {
    $this->get(route('filament.admin.resources.orders.index'))->assertRedirect();
});

test('si l\'enregistrement échoue après la création chez Chronopost : PDF supprimé et LT annulée', function () {
    $order = labelOrder();
    // Une étiquette annulée porte déjà ce numéro de LT : l'insertion viole l'unicité.
    $order->shippingLabels()->create(['skybill_number' => FIXTURE_SKYBILL, 'pdf_path' => 'x.pdf', 'weight_g' => 1]);
    $order->shippingLabels()->update(['cancelled_at' => now()]);

    expect(fn () => app(GenerateShippingLabelAction::class)->execute($order, null))
        ->toThrow(ShippingLabelFailed::class, 'pas pu être enregistrée');

    expect(Storage::disk('local')->allFiles())->toBe([])
        ->and($this->fake->requests)->toHaveCount(2)
        ->and($this->fake->requests[1])->toContain('cancelListSkybill')->toContain(FIXTURE_SKYBILL);
});

test('une opération déjà en cours sur l\'étiquette est refusée sans appeler Chronopost', function () {
    $order = labelOrder();
    $lock = GenerateShippingLabelAction::lockFor($order);
    $lock->get();

    try {
        expect(fn () => app(GenerateShippingLabelAction::class)->execute($order, null))->toThrow(ShippingLabelFailed::class, 'déjà en cours')
            ->and(fn () => app(CancelShippingLabelAction::class)->execute($order))->toThrow(ShippingLabelFailed::class, 'déjà en cours')
            ->and($this->fake->requests)->toBe([]);
    } finally {
        $lock->release();
    }
});

test('l\'appel à Chronopost n\'est pas fait dans une transaction BDD', function () {
    $levels = [];
    $this->fake->onRequest = function () use (&$levels): void {
        $levels[] = DB::transactionLevel();
    };

    app(GenerateShippingLabelAction::class)->execute(labelOrder(), null);

    // RefreshDatabase ouvre déjà une transaction de test (niveau 1) : aucune de plus pendant l'appel.
    expect($levels)->toBe([1]);
});

test('l\'étiquette d\'une commande remboursée ou annulée avant expédition peut être annulée', function (OrderStatus $status) {
    $order = labelOrder();
    app(GenerateShippingLabelAction::class)->execute($order, null);
    $order->forceFill(['status' => $status])->save();

    expect(CancelShippingLabelAction::isAvailableFor($order->fresh()))->toBeTrue();
    app(CancelShippingLabelAction::class)->execute($order);

    expect($order->fresh()->activeShippingLabel)->toBeNull();
})->with([OrderStatus::Refunded, OrderStatus::Cancelled]);

test('une commande remboursée APRÈS expédition ne propose pas l\'annulation de l\'étiquette', function () {
    $order = labelOrder();
    app(GenerateShippingLabelAction::class)->execute($order, null);
    $order->forceFill(['status' => OrderStatus::Refunded, 'shipped_at' => now()])->save();

    expect(CancelShippingLabelAction::isAvailableFor($order->fresh()))->toBeFalse();
});

test('le poids déclaré ajoute le poids d\'emballage paramétré', function () {
    $shipping = app(ShippingSettings::class);
    $shipping->packaging_weight_g = 250;
    $shipping->save();

    $label = app(GenerateShippingLabelAction::class)->execute(labelOrder(), null);

    expect($label->weight_g)->toBe(1450);
});

test('annulation refusée peu après la création : message expliquant le délai Chronopost', function () {
    $this->travelTo(now()->setTimezone('Europe/Paris')->setTime(12, 0));
    $order = labelOrder();
    $label = app(GenerateShippingLabelAction::class)->execute($order, null);
    $this->fake->responsesByMethod['cancelListSkybill'] = FakeChronopostSoapClientFactory::cancelResponse(1, 'System error');
    $this->travel(2)->minutes();

    expect(fn () => app(CancelShippingLabelAction::class)->execute($order))
        ->toThrow(ShippingLabelFailed::class, 'environ 10 minutes après sa création : réessayez à partir de 12:11');
    expect($label->fresh()->cancelled_at)->toBeNull();
});

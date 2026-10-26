<?php

declare(strict_types=1);

use App\Livewire\CheckoutWizard;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\Cart\CartService;
use App\Services\Chronopost\SoapClientFactory;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\FakeChronopostSoapClientFactory;

/** Tunnel arrivé à l'étape « Relais » avec un panier expédiable. */
function relayStep(): Testable
{
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['thursday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->free_shipping_enabled = false;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 30000, 'price_ht' => 825]);
    app(CartService::class)->add(Product::factory()->create(['price_ttc' => 1000]), 1);

    return Livewire::test(CheckoutWizard::class)
        ->set('email', 'cliente@example.com')->set('first_name', 'Jeanne')->set('last_name', 'Dupont')
        ->set('phone', '0612345678')->set('billing_line1', '1 rue du Pain')
        ->set('billing_postal_code', '50300')->set('billing_city', 'Avranches')
        ->call('goToStep', 2);
}

test('la recherche est pré-remplie avec l\'adresse de livraison', function () {
    relayStep()->assertSet('relay_postal_code', '50300')->assertSet('relay_city', 'Avranches');
});

test('la recherche affiche la liste des relais (nom, adresse, distance)', function () {
    relayStep()->set('relay_postal_code', '33000')->set('relay_city', 'Bordeaux')
        ->call('searchRelays')
        ->assertHasNoErrors()
        ->assertCount('relayResults', 10)
        ->assertSee('LA POSTE CARREPRO MERIADECK')
        ->assertSee('5 rue père dieuzaide')
        ->assertSee('0,2 km');
});

test('le relais choisi est copié dans la commande à partir des données Chronopost', function () {
    relayStep()->set('relay_postal_code', '33000')->set('relay_city', 'Bordeaux')
        ->call('searchRelays')->set('relay_id', '8339S')
        ->call('goToStep', 3)->assertSet('step', 3)
        ->call('goToStep', 4)->set('cgvAccepted', true)->set('paymentMethod', 'stripe')
        ->call('pay')->assertHasNoErrors();

    $order = Order::query()->sole();
    expect($order->relay_id)->toBe('8339S')
        ->and($order->relay_name)->toBe('LA POSTE CARREPRO MERIADECK')
        ->and($order->relay_snapshot)->toMatchArray([
            'chronopost_id' => '8339S',
            'address_line1' => '5 rue père dieuzaide',
            'postal_code' => '33000',
            'city' => 'BORDEAUX',
        ])
        ->and($order->relay_snapshot['opening_hours'])->toStartWith('Lun. ');
});

test('un identifiant de relais absent de la recherche est refusé', function () {
    relayStep()->set('relay_postal_code', '33000')->set('relay_city', 'Bordeaux')
        ->call('searchRelays')->set('relay_id', 'FAUX1')
        ->call('goToStep', 3)
        ->assertHasErrors(['relay_id'])
        ->assertSet('step', 2);
});

test('la liste des relais ne peut pas être modifiée depuis le navigateur', function () {
    relayStep()->set('relayResults', [['id' => 'FAUX1', 'name' => 'Relais pirate']]);
})->throws(CannotUpdateLockedPropertyException::class);

test('la ville de recherche ne peut pas être falsifiée après coup', function () {
    relayStep()->set('relaySearchCity', 'AILLEURS');
})->throws(CannotUpdateLockedPropertyException::class);

test('les codes postaux d\'outre-mer sont refusés', function (string $postalCode) {
    relayStep()->set('relay_postal_code', $postalCode)->set('relay_city', 'Ville')
        ->call('searchRelays')
        ->assertHasErrors(['relay_postal_code'])
        ->assertSet('relayResults', []);
})->with(['97400', '98800']);

test('un code postal ou une ville manquants sont refusés', function () {
    relayStep()->set('relay_postal_code', '503')->set('relay_city', '')
        ->call('searchRelays')
        ->assertHasErrors(['relay_postal_code', 'relay_city']);
});

test('Chronopost injoignable : message clair, le client n\'est pas bloqué sur un écran vide', function () {
    app()->instance(SoapClientFactory::class, new FakeChronopostSoapClientFactory(null));

    relayStep()->set('relay_postal_code', '33000')->set('relay_city', 'Bordeaux')
        ->call('searchRelays')
        ->assertHasErrors(['relay_postal_code'])
        ->assertSee('Service de points relais momentanément indisponible');
});

test('aucun relais trouvé : message invitant à vérifier la ville', function () {
    $empty = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
        .'<ns1:recherchePointChronopostInterResponse xmlns:ns1="http://cxf.rechercheBt.soap.chronopost.fr/">'
        .'<return><errorCode>0</errorCode><errorMessage>Code retour OK</errorMessage></return>'
        .'</ns1:recherchePointChronopostInterResponse></soap:Body></soap:Envelope>';
    app()->instance(SoapClientFactory::class, new FakeChronopostSoapClientFactory($empty));

    relayStep()->set('relay_postal_code', '33000')->set('relay_city', 'Bordeaux')
        ->call('searchRelays')
        ->assertHasErrors(['relay_postal_code'])
        ->assertSee('Aucun point relais trouvé');
});

test('les recherches répétées sont limitées', function () {
    $component = relayStep()->set('relay_postal_code', '33000')->set('relay_city', 'Bordeaux');
    RateLimiter::clear('relay-search:127.0.0.1');

    for ($i = 0; $i < 10; $i++) {
        $component->call('searchRelays')->assertHasNoErrors();
    }

    $component->call('searchRelays')->assertHasErrors(['relay_postal_code']);
});

<?php

declare(strict_types=1);

use App\Livewire\CheckoutWizard;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\User;
use App\Rules\Turnstile;
use App\Services\Cart\CartService;
use App\Settings\ShippingSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Messages en français (06/11/2026) : sans lang/fr, le client voyait les clés
 * brutes « validation.required » (formulaires) et « auth.failed » (connexion).
 */
function flattenTranslationKeys(array $lines, string $prefix = ''): array
{
    $keys = [];
    foreach ($lines as $key => $value) {
        if (in_array($key, ['attributes', 'custom'], true)) {
            continue;
        }
        $keys = is_array($value)
            ? array_merge($keys, flattenTranslationKeys($value, "{$prefix}{$key}."))
            : [...$keys, "{$prefix}{$key}"];
    }

    return $keys;
}

test('chaque message anglais de Laravel a sa traduction française', function (string $file) {
    $english = flattenTranslationKeys(require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php"));
    $french = flattenTranslationKeys(require lang_path("fr/{$file}.php"));

    expect(array_values(array_diff($english, $french)))->toBe([]);
})->with(['validation', 'auth', 'passwords', 'pagination']);

test('la langue de l\'application est le français', function () {
    expect(app()->getLocale())->toBe('fr')
        ->and(__('validation.required', ['attribute' => 'e-mail']))->toBe('Le champ e-mail est obligatoire.');
});

test('tunnel : champs vides et téléphone invalide, messages en français', function () {
    $settings = app(ShippingSettings::class);
    $settings->shipping_weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    $settings->order_cutoff_time = '15:00';
    $settings->production_lead_days = 1;
    $settings->save();
    ShippingRate::factory()->create(['min_weight_g' => 0, 'max_weight_g' => 5000, 'price_ht' => 500]);
    app(CartService::class)->add(Product::factory()->create(['price_ttc' => 3000]), 1);

    $wizard = Livewire::test(CheckoutWizard::class)
        ->set('phone', '12345')
        ->call('goToStep', 2);

    $errors = $wizard->errors();
    expect($errors->first('email'))->toBe('Le champ e-mail est obligatoire.')
        ->and($errors->first('first_name'))->toBe('Le champ prénom est obligatoire.')
        ->and($errors->first('billing_line1'))->toBe('Le champ adresse de facturation est obligatoire.')
        ->and($errors->first('phone'))->toBe('Le format du champ téléphone n\'est pas valide.');
});

test('connexion : mauvais identifiants, message en français', function () {
    $this->post(route('login'), ['email' => 'inconnu@example.com', 'password' => 'faux-mot-de-passe'])
        ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte.']);
});

test('mot de passe oublié : même message neutre, compte existant ou non', function () {
    Notification::fake();
    // Captcha du formulaire : réponse Turnstile simulée (aucun appel réel).
    config(['services.turnstile.site_key' => 'site-key-test', 'services.turnstile.secret_key' => 'secret-test']);
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);
    $user = User::factory()->create();
    $neutral = 'Si un compte correspond à cette adresse, vous allez recevoir un e-mail avec le lien de réinitialisation.';

    $this->post(route('password.email'), ['email' => $user->email, 'cf-turnstile-response' => 'jeton'])
        ->assertSessionHas('status', $neutral);

    $this->post(route('password.email'), ['email' => 'inconnu@example.com', 'cf-turnstile-response' => 'jeton'])
        ->assertSessionHasErrors(['email' => $neutral]);
});

test('inscription : mot de passe trop court, confirmation et e-mail déjà utilisé, messages en français', function () {
    config(['services.turnstile.site_key' => 'site-key-test', 'services.turnstile.secret_key' => 'secret-test']);
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);
    $existing = User::factory()->create();

    $this->post(route('register.store'), [
        'account_type' => 'individual',
        'first_name' => 'Jeanne',
        'last_name' => 'Dupont',
        'phone' => '0612345678',
        'email' => $existing->email,
        'password' => 'abc',
        'password_confirmation' => 'xyz',
        'cf-turnstile-response' => 'jeton',
    ])->assertSessionHasErrors([
        'email' => 'La valeur du champ e-mail est déjà utilisée.',
        'password' => 'Le champ mot de passe doit contenir au moins 8 caractères.',
    ]);
});

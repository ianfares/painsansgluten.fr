<?php

declare(strict_types=1);

use App\Enums\ProRequestStatus;
use App\Mail\Admin\NewProAccountRequestMail;
use App\Mail\ProAccountRequestReceivedMail;
use App\Models\Category;
use App\Models\ProAccountRequest;
use App\Rules\Turnstile;
use App\Settings\ShopSettings;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('pro-request:127.0.0.1');
    config(['services.turnstile.site_key' => 'site-key-test', 'services.turnstile.secret_key' => 'secret-test']);
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);

    $shop = app(ShopSettings::class);
    $shop->admin_notification_email = 'admin@example.test';
    $shop->save();

    Category::factory()->create(['name' => 'Pains', 'is_active' => true]);
    Category::factory()->create(['name' => 'Catégorie cachée', 'is_active' => false]);
});

function proPayload(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Pizzeria Exemple',
        'siret' => '73282932000074',
        'activity_type' => 'Pizzeria',
        'activity_other' => '',
        'contact_last_name' => 'Exemple',
        'contact_first_name' => 'Marie',
        'job_title' => 'Gérante',
        'email' => 'marie@pizzeria.example.test',
        'phone' => '06 12 34 56 78',
        'address_line1' => '1 rue du Test',
        'postal_code' => '50300',
        'city' => 'Avranches',
        'description' => 'Nous cherchons des pains sans gluten pour notre restaurant.',
        'consent' => '1',
        'cf-turnstile-response' => 'jeton',
    ], $overrides);
}

test('la page affiche le texte de présentation, le formulaire, le widget et le lien vers la confidentialité', function () {
    $this->get('/professionnels')
        ->assertOk()
        ->assertSee('Espace Professionnels', false)
        ->assertSee('Prix dégressifs selon les volumes')
        ->assertSee('Demande de compte professionnel')
        ->assertSee('Boulangerie / Pâtisserie')
        ->assertSee('data-sitekey="site-key-test"', false)
        ->assertSee('/politique-de-confidentialite', false);
});

test('le texte de présentation édité en BO est affiché, purifié', function () {
    $shop = app(ShopSettings::class);
    $shop->pro_intro_html = '<p>Bienvenue aux pros</p><script>alert(1)</script>';
    $shop->save();

    $this->get('/professionnels')->assertOk()->assertSee('Bienvenue aux pros')->assertDontSee('alert(1)', false);
});

test('la page professionnels est dans le sitemap', function () {
    $this->get('/sitemap.xml')->assertOk()->assertSee(route('pro.request.create'), false);
});

test('une demande valide est stockée et déclenche deux emails en file d\'attente', function () {
    $this->post('/professionnels', proPayload())
        ->assertRedirect(route('pro.request.create'))
        ->assertSessionHas('pro_sent');

    $stored = ProAccountRequest::query()->sole();
    expect($stored->company_name)->toBe('Pizzeria Exemple')
        ->and($stored->status)->toBe(ProRequestStatus::Pending)
        ->and($stored->consent_at)->not->toBeNull();

    Mail::assertQueued(ProAccountRequestReceivedMail::class, fn ($mail) => $mail->hasTo('marie@pizzeria.example.test'));
    Mail::assertQueued(NewProAccountRequestMail::class, fn ($mail) => $mail->hasTo('admin@example.test'));
});

test('les espaces du SIRET sont normalisés', function () {
    $this->post('/professionnels', proPayload(['siret' => '732 829 320 00074']))
        ->assertSessionHasNoErrors();

    $stored = ProAccountRequest::query()->sole();
    expect($stored->siret)->toBe('73282932000074');
});

test('un SIRET dont la clé de Luhn est fausse est refusé', function () {
    $this->post('/professionnels', proPayload(['siret' => '73282932000075']))->assertSessionHasErrors('siret');
    $this->post('/professionnels', proPayload(['siret' => '1234']))->assertSessionHasErrors('siret');

    expect(ProAccountRequest::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('sans consentement, la demande est refusée', function () {
    $this->post('/professionnels', proPayload(['consent' => null]))->assertSessionHasErrors('consent');

    expect(ProAccountRequest::query()->count())->toBe(0);
});

test('« Autre » exige une précision, et un type inconnu est refusé', function () {
    $this->post('/professionnels', proPayload(['activity_type' => 'Autre', 'activity_other' => '']))->assertSessionHasErrors('activity_other');
    $this->post('/professionnels', proPayload(['activity_type' => 'Autre', 'activity_other' => 'Traiteur']))->assertSessionHasNoErrors();
    $this->post('/professionnels', proPayload(['activity_type' => 'Inconnu']))->assertSessionHasErrors('activity_type');
});

test('la description est facultative mais limitée à 2000 caractères', function () {
    $this->post('/professionnels', proPayload(['description' => '']))->assertSessionHasNoErrors();
    $this->post('/professionnels', proPayload(['description' => str_repeat('a', 2001)]))->assertSessionHasErrors('description');
});

test('Turnstile échoué : rien n\'est enregistré ni envoyé', function () {
    // Repart d'un client HTTP neuf : le premier bouchon déclaré (succès, beforeEach) l'emporterait sinon.
    Http::swap(new HttpFactory);
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => false])]);

    $this->post('/professionnels', proPayload())->assertSessionHasErrors('cf-turnstile-response');

    expect(ProAccountRequest::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('à partir de la 4e demande acceptée dans l\'heure, la demande est refusée', function () {
    foreach (range(1, 3) as $i) {
        $this->post('/professionnels', proPayload(['email' => "pro{$i}@example.test"]))->assertSessionHas('pro_sent');
    }

    $this->post('/professionnels', proPayload(['email' => 'pro4@example.test']))
        ->assertSessionHas('pro_error')
        ->assertSessionMissing('pro_sent');

    expect(ProAccountRequest::query()->count())->toBe(3);
});

test('les demandes invalides ne consomment pas le quota de 3 demandes par heure', function () {
    foreach (range(1, 4) as $i) {
        $this->post('/professionnels', proPayload(['siret' => '1']))->assertSessionHasErrors('siret');
    }

    $this->post('/professionnels', proPayload())->assertSessionHas('pro_sent');
});

test('le statut ne peut pas être imposé par le formulaire', function () {
    $this->post('/professionnels', proPayload(['status' => 'approved', 'admin_comment' => 'x']));

    $stored = ProAccountRequest::query()->sole();
    expect($stored->status)->toBe(ProRequestStatus::Pending)->and($stored->admin_comment)->toBeNull();
});

test('une demande avec seulement nom, prénom, téléphone, email et consentement est enregistrée', function () {
    $this->post('/professionnels', [
        'contact_last_name' => 'Exemple',
        'contact_first_name' => 'Marie',
        'phone' => '06 12 34 56 78',
        'email' => 'marie@pizzeria.example.test',
        'consent' => '1',
        'cf-turnstile-response' => 'jeton',
    ])->assertSessionHasNoErrors()->assertSessionHas('pro_sent');

    $stored = ProAccountRequest::query()->sole();
    expect($stored->contact_last_name)->toBe('Exemple')
        ->and($stored->company_name)->toBeNull()
        ->and($stored->siret)->toBeNull()
        ->and($stored->activity_type)->toBeNull()
        ->and($stored->address_line1)->toBeNull()
        ->and($stored->postal_code)->toBeNull()
        ->and($stored->city)->toBeNull()
        ->and($stored->description)->toBeNull();

    Mail::assertQueued(ProAccountRequestReceivedMail::class);
    Mail::assertQueued(NewProAccountRequestMail::class);
});

test('chacun des quatre champs obligatoires manquants est refusé', function (string $field) {
    $this->post('/professionnels', proPayload([$field => '']))->assertSessionHasErrors($field);

    expect(ProAccountRequest::query()->count())->toBe(0);
})->with(['contact_last_name', 'contact_first_name', 'phone', 'email']);

test('les champs facultatifs, une fois remplis, restent validés', function () {
    $this->post('/professionnels', proPayload(['postal_code' => '5030']))->assertSessionHasErrors('postal_code');
    $this->post('/professionnels', proPayload(['siret' => '73282932000075']))->assertSessionHasErrors('siret');
    $this->post('/professionnels', proPayload(['activity_type' => 'Inconnu']))->assertSessionHasErrors('activity_type');

    expect(ProAccountRequest::query()->count())->toBe(0);
});

test('la page n\'affiche plus les champs retirés', function () {
    $this->get('/professionnels')
        ->assertOk()
        ->assertDontSee('Produits qui vous intéressent')
        ->assertDontSee('Volumes / fréquence')
        ->assertDontSee('TVA intracommunautaire')
        ->assertDontSee('products_of_interest', false)
        ->assertDontSee('vat_number', false)
        ->assertDontSee('name="volumes"', false);
});

test('les emails de la demande se rendent sans raison sociale', function () {
    $bare = ProAccountRequest::factory()->make(['id' => 1, 'company_name' => null, 'activity_type' => null]);

    expect((new ProAccountRequestReceivedMail($bare))->render())->toContain('votre demande de compte professionnel')
        ->and((new NewProAccountRequestMail($bare))->render())->toMatch('/Société : <strong[^>]*>—<\/strong>/u');
});

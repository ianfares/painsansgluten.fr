<?php

declare(strict_types=1);

use App\Mail\ContactMessageMail;
use App\Models\Page;
use App\Rules\Turnstile;
use App\Settings\ShopSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config(['services.turnstile.site_key' => 'site-key-test', 'services.turnstile.secret_key' => 'secret-test']);

    $shop = app(ShopSettings::class);
    $shop->contact_email = 'boutique@example.test';
    $shop->save();
});

function contactPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Marie Exemple',
        'email' => 'marie@example.test',
        'phone' => '06 12 34 56 78',
        'message' => "Bonjour,\nlivrez-vous à Granville ?",
        'cf-turnstile-response' => 'jeton-turnstile',
    ], $overrides);
}

test('la page contact affiche le formulaire et le widget Turnstile', function () {
    $this->get('/contact')
        ->assertOk()
        ->assertSee('Écrivez-nous')
        ->assertSee('data-sitekey="site-key-test"', false)
        ->assertSee('challenges.cloudflare.com/turnstile', false);
});

test('le texte de la page contact rédigé en BO s\'affiche au-dessus du formulaire, s\'il est publié', function () {
    Page::factory()->create(['slug' => 'contact', 'title' => 'Nous contacter', 'content' => '<p>Marché d\'Avranches le samedi</p>', 'is_published' => true]);

    $this->get('/contact')->assertOk()->assertSee('Nous contacter')->assertSee('Marché d\'Avranches le samedi', false);
});

test('un message valide est envoyé à la boutique, avec « Répondre » vers le visiteur', function () {
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);

    $this->post('/contact', contactPayload())
        ->assertRedirect(route('contact'))
        ->assertSessionHas('contact_sent');

    Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail) {
        $mail->assertSeeInHtml('livrez-vous à Granville ?');

        return $mail->hasTo('boutique@example.test') && $mail->hasReplyTo('marie@example.test');
    });
    Http::assertSent(fn ($request) => $request['secret'] === 'secret-test' && $request['response'] === 'jeton-turnstile');
});

test('sans validation Turnstile, rien n\'est envoyé', function () {
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    $this->post('/contact', contactPayload())->assertSessionHasErrors('cf-turnstile-response');

    Mail::assertNothingQueued();
});

test('sans jeton Turnstile, Cloudflare n\'est même pas interrogé', function () {
    Http::fake();

    $this->post('/contact', contactPayload(['cf-turnstile-response' => '']))->assertSessionHasErrors('cf-turnstile-response');

    Http::assertNothingSent();
    Mail::assertNothingQueued();
});

test('si Cloudflare est injoignable, le message est refusé', function () {
    Http::fake([Turnstile::VERIFY_URL => fn () => throw new ConnectionException('timeout')]);

    $this->post('/contact', contactPayload())->assertSessionHasErrors('cf-turnstile-response');

    Mail::assertNothingQueued();
});

test('les champs obligatoires sont vérifiés, avec des messages en français', function () {
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);

    $this->post('/contact', contactPayload(['name' => '', 'email' => 'pas-un-email', 'message' => 'court']))
        ->assertSessionHasErrors([
            'name' => 'Merci d\'indiquer votre nom.',
            'email' => 'Cette adresse email n\'est pas valide.',
            'message' => 'Votre message est un peu court (10 caractères minimum).',
        ]);
});

test('sans adresse de réception paramétrée, le visiteur voit un message et rien n\'est perdu en silence', function () {
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);
    $shop = app(ShopSettings::class);
    $shop->contact_email = null;
    $shop->admin_notification_email = null;
    $shop->save();

    $this->post('/contact', contactPayload())->assertSessionHas('contact_error');

    Mail::assertNothingQueued();
});

test('l\'envoi est limité à 5 messages par minute', function () {
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);

    foreach (range(1, 5) as $i) {
        $this->post('/contact', contactPayload())->assertRedirect();
    }

    $this->post('/contact', contactPayload())->assertStatus(429);
});

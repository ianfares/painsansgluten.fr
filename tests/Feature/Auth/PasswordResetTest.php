<?php

declare(strict_types=1);

use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);
});

test('un client peut demander un lien de réinitialisation', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email, 'cf-turnstile-response' => 'jeton']);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('un client peut réinitialiser son mot de passe avec un token valide', function () {
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ]);

    expect(Hash::check('nouveau-mot-de-passe', $user->fresh()->password))->toBeTrue();
});

test('la réinitialisation échoue avec un token invalide', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalide',
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ]);

    $response->assertSessionHasErrors('email');
});

test('sans jeton anti-robot, aucun lien de réinitialisation n\'est envoyé', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasErrors(['cf-turnstile-response' => 'Merci de valider la vérification anti-robot.']);

    Notification::assertNothingSent();
});

test('la page mot de passe oublié affiche le widget anti-robot', function () {
    $this->get(route('password.request'))->assertOk()->assertSee('cf-turnstile', false);
});

test('la réinitialisation elle-même (lien reçu par email) ne demande pas de captcha', function () {
    Http::fake();
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token, 'email' => $user->email,
        'password' => 'nouveau-mot-de-passe', 'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertSessionHasNoErrors();

    Http::assertNothingSent();
});

test('le lien de réinitialisation est valable 3 heures, puis expire', function () {
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);
    $payload = fn () => [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ];

    $this->travel(181)->minutes();

    $this->post(route('password.update'), $payload())
        ->assertSessionHasErrors(['email' => __('passwords.token')]);
    expect(Hash::check('nouveau-mot-de-passe', $user->fresh()->password))->toBeFalse();
});

test('un lien encore valide après 2 h 59 fonctionne', function () {
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);

    $this->travel(179)->minutes();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ]);

    expect(Hash::check('nouveau-mot-de-passe', $user->fresh()->password))->toBeTrue();
});

test('lien expiré : le message invite à redemander un lien via « Mot de passe oublié »', function () {
    expect(__('passwords.token'))->toContain('expiré')->toContain('Mot de passe oublié');

    $user = User::factory()->create();
    $this->get(route('password.reset', ['token' => 'x', 'email' => $user->email]))
        ->assertOk()
        ->assertSee('Lien expiré ?')
        ->assertSee(route('password.request'), false);
});

test('l\'email indique la durée de 3 heures et le lien « Mot de passe oublié »', function () {
    $user = User::factory()->create();
    $html = (string) (new ResetPassword('jeton'))->toMail($user)->render();

    expect($html)->toContain('valable 3 heures')
        ->toContain('Mot de passe oublié')
        ->toContain(route('password.request'));
});

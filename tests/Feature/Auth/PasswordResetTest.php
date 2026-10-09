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

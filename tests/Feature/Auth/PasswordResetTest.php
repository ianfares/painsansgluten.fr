<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('un client peut demander un lien de réinitialisation', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

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

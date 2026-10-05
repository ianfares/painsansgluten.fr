<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('un visiteur peut créer un compte avec prénom, nom, téléphone, email et mot de passe', function () {
    Notification::fake();

    $response = $this->post(route('register'), [
        'first_name' => 'Camille',
        'last_name' => 'Durand',
        'phone' => '0612345678',
        'email' => 'camille@example.com',
        'password' => 'mot-de-passe-sur',
        'password_confirmation' => 'mot-de-passe-sur',
    ]);

    $user = User::query()->where('email', 'camille@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Camille')
        ->and($user->last_name)->toBe('Durand')
        ->and($user->phone)->toBe('0612345678')
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
    $this->assertAuthenticatedAs($user);
});

test('l\'inscription échoue sans téléphone (obligatoire pour le SMS Chronopost)', function () {
    $response = $this->post(route('register'), [
        'first_name' => 'Camille',
        'last_name' => 'Durand',
        'email' => 'camille@example.com',
        'password' => 'mot-de-passe-sur',
        'password_confirmation' => 'mot-de-passe-sur',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});

test('un email de vérification non cliqué bloque l\'accès à l\'espace client', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/mon-compte');

    $response->assertRedirect(route('verification.notice'));
});

test('vérifier son email donne accès à l\'espace client', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

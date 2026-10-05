<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('un visiteur non connecté est redirigé vers la connexion', function () {
    $this->get(route('compte.dashboard'))->assertRedirect(route('login'));
});

test('un client connecté et vérifié voit son tableau de bord', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('compte.dashboard'))->assertOk()->assertSee($user->first_name);
});

test('un client ne voit que ses propres commandes', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Order::factory()->create(['user_id' => $user->id, 'number' => 'C2026-00001']);
    Order::factory()->create(['user_id' => $otherUser->id, 'number' => 'C2026-00002']);

    $response = $this->actingAs($user)->get(route('compte.orders'));

    $response->assertSee('C2026-00001');
    $response->assertDontSee('C2026-00002');
});

test('un client peut mettre à jour son adresse de facturation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('compte.address.update'), [
        'first_name' => 'Camille',
        'last_name' => 'Durand',
        'line1' => '12 rue des Lilas',
        'postal_code' => '50300',
        'city' => 'Avranches',
    ])->assertRedirect();

    expect($user->addresses()->first()->city)->toBe('Avranches');
});

test('un client peut demander la suppression de son compte', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('compte.deletion.request'))->assertRedirect();

    expect($user->fresh()->deletion_requested_at)->not->toBeNull();
});

test('un client peut changer son mot de passe', function () {
    $user = User::factory()->create(['password' => bcrypt('ancien-mot-de-passe')]);

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'ancien-mot-de-passe',
        'password' => 'nouveau-mot-de-passe',
        'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertSessionDoesntHaveErrors();

    expect(Hash::check('nouveau-mot-de-passe', $user->fresh()->password))->toBeTrue();
});

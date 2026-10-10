<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\ProStatus;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Mail\AccountCreatedByAdminMail;
use App\Mail\Admin\NewProAccountToValidateMail;
use App\Mail\ProAccountValidatedMail;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use App\Rules\Turnstile;
use App\Settings\ShopSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

function registerPayload(array $overrides = []): array
{
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);

    return array_merge([
        'first_name' => 'Camille',
        'last_name' => 'Durand',
        'phone' => '0612345678',
        'email' => 'camille@example.com',
        'password' => 'mot-de-passe-sur',
        'password_confirmation' => 'mot-de-passe-sur',
        'cf-turnstile-response' => 'jeton',
    ], $overrides);
}

beforeEach(function () {
    Mail::fake();
    Notification::fake();
    $shop = app(ShopSettings::class);
    $shop->admin_notification_email = 'admin@example.test';
    $shop->save();
});

// --- Inscription ------------------------------------------------------------

test('l\'inscription particulier crée un compte individual sans statut pro', function () {
    $this->post(route('register'), registerPayload())->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'camille@example.com')->firstOrFail();
    expect($user->account_type)->toBe(AccountType::Individual)
        ->and($user->pro_status)->toBeNull()
        ->and($user->isApprovedPro())->toBeFalse();
    Mail::assertNothingQueued();
});

test('l\'inscription pro crée un compte en attente et prévient l\'admin', function () {
    $this->post(route('register'), registerPayload([
        'account_type' => 'pro', 'company_name' => 'Boulangerie Test', 'siret' => '732 829 320 00074',
    ]))->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'camille@example.com')->firstOrFail();
    expect($user->account_type)->toBe(AccountType::Pro)
        ->and($user->company_name)->toBe('Boulangerie Test')
        ->and($user->siret)->toBe('73282932000074')
        ->and($user->pro_status)->toBe(ProStatus::Pending)
        ->and($user->isApprovedPro())->toBeFalse();
    Mail::assertQueued(NewProAccountToValidateMail::class, fn ($m) => $m->hasTo('admin@example.test'));
});

test('l\'inscription pro exige raison sociale et SIRET valide', function () {
    $this->post(route('register'), registerPayload(['account_type' => 'pro']))
        ->assertSessionHasErrors(['company_name', 'siret']);
    $this->post(route('register'), registerPayload(['account_type' => 'pro', 'company_name' => 'X', 'siret' => '12345678901234']))
        ->assertSessionHasErrors('siret');
    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

test('l\'inscription ne permet pas de s\'attribuer le statut pro validé', function () {
    $this->post(route('register'), registerPayload([
        'account_type' => 'pro', 'company_name' => 'Boulangerie Test', 'siret' => '73282932000074',
        'pro_status' => 'approved', 'lab_pickup_allowed' => '1', 'deactivated_at' => null,
    ]))->assertSessionHasNoErrors();

    $user = User::query()->firstOrFail();
    expect($user->pro_status)->toBe(ProStatus::Pending)->and($user->lab_pickup_allowed)->toBeFalse();
});

test('un type de compte inconnu est refusé', function () {
    $this->post(route('register'), registerPayload(['account_type' => 'admin']))->assertSessionHasErrors('account_type');
});

// --- Espace client ----------------------------------------------------------

test('« Mes informations » affiche le statut pro et permet la modification tant qu\'il est en attente', function () {
    $user = User::factory()->pro(false)->create();

    $this->actingAs($user)->get(route('compte.informations'))
        ->assertOk()->assertSee('en attente de validation');

    $this->actingAs($user)->put(route('compte.company.update'), ['company_name' => 'Nouvelle SARL', 'siret' => '73282932000074'])
        ->assertSessionHasNoErrors();
    expect($user->refresh()->company_name)->toBe('Nouvelle SARL');
});

test('raison sociale et SIRET ne sont plus modifiables par le client une fois le compte validé', function () {
    $user = User::factory()->pro()->create(['company_name' => 'Initiale']);

    $this->actingAs($user)->get(route('compte.informations'))->assertOk()->assertSee('validé');
    $this->actingAs($user)->put(route('compte.company.update'), ['company_name' => 'Pirate', 'siret' => '73282932000074'])->assertForbidden();
    expect($user->refresh()->company_name)->toBe('Initiale');
});

test('un particulier ne peut pas utiliser la route entreprise', function () {
    $this->actingAs(User::factory()->create())->put(route('compte.company.update'), ['company_name' => 'X', 'siret' => '73282932000074'])->assertForbidden();
});

// --- Admin : validation pro -------------------------------------------------

test('l\'admin valide un compte pro : statut, date et email au client', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $user = User::factory()->pro(false)->create();

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->callAction('approvePro')->assertHasNoActionErrors();

    $user->refresh();
    expect($user->isApprovedPro())->toBeTrue()->and($user->pro_approved_at)->not->toBeNull();
    Mail::assertQueued(ProAccountValidatedMail::class, fn ($m) => $m->hasTo($user->email));
});

test('le retrait au laboratoire ne peut être activé que pour un pro validé', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $pending = User::factory()->pro(false)->create();
    $approved = User::factory()->pro()->create();

    Livewire::test(EditUser::class, ['record' => $approved->getRouteKey()])
        ->fillForm(['lab_pickup_allowed' => true])->call('save')->assertHasNoFormErrors();
    expect($approved->refresh()->lab_pickup_allowed)->toBeTrue();

    Livewire::test(EditUser::class, ['record' => $pending->getRouteKey()])
        ->fillForm(['lab_pickup_allowed' => true])->call('save');
    expect($pending->refresh()->lab_pickup_allowed)->toBeFalse();
});

// --- Désactivation ----------------------------------------------------------

test('la désactivation coupe les sessions, clôt la demande de suppression et conserve les commandes', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $user = User::factory()->create(['deletion_requested_at' => now()]);
    $order = Order::factory()->create(['user_id' => $user->id]);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->callAction('deactivate')->assertHasNoActionErrors();

    $user->refresh();
    expect($user->deactivated_at)->not->toBeNull()
        ->and($user->deletion_requested_at)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(Order::query()->whereKey($order->id)->exists())->toBeTrue();
});

test('un compte désactivé ne peut pas se connecter, avec le bon mot de passe', function () {
    $user = User::factory()->deactivated()->create(['password' => Hash::make('mot-de-passe-sur')]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'mot-de-passe-sur'])
        ->assertSessionHasErrors(['email' => 'Ce compte est désactivé. Contactez-nous via le formulaire de contact.']);
    $this->assertGuest();
});

test('un compte désactivé avec un mauvais mot de passe reçoit l\'échec générique (pas d\'énumération)', function () {
    $user = User::factory()->deactivated()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'faux'])->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->not->toContain('désactivé');
});

test('les connexions refusées d\'un compte désactivé comptent dans la limitation', function () {
    $user = User::factory()->deactivated()->create(['password' => Hash::make('mot-de-passe-sur')]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login'), ['email' => $user->email, 'password' => 'mot-de-passe-sur']);
    }

    $this->post(route('login'), ['email' => $user->email, 'password' => 'mot-de-passe-sur'])->assertStatus(429);
});

test('un client connecté puis désactivé est déconnecté à la requête suivante', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('compte.dashboard'))->assertOk();

    $user->forceFill(['deactivated_at' => now()])->save();

    $this->get(route('compte.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('la réinitialisation de mot de passe ne débloque pas un compte désactivé', function () {
    $user = User::factory()->deactivated()->create(['password' => Hash::make('ancien-mot-de-passe')]);
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token, 'email' => $user->email,
        'password' => 'nouveau-mot-de-passe', 'password_confirmation' => 'nouveau-mot-de-passe',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('ancien-mot-de-passe', $user->refresh()->password))->toBeTrue();
    $this->post(route('login'), ['email' => $user->email, 'password' => 'nouveau-mot-de-passe']);
    $this->assertGuest();
});

test('la réactivation rend la connexion possible', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $user = User::factory()->deactivated()->create(['password' => Hash::make('mot-de-passe-sur')]);

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->callAction('reactivate')->assertHasNoActionErrors();
    expect($user->refresh()->deactivated_at)->toBeNull();

    auth()->shouldUse('web');
    $this->post(route('login'), ['email' => $user->email, 'password' => 'mot-de-passe-sur']);
    $this->assertAuthenticatedAs($user);
});

// --- Création manuelle ------------------------------------------------------

test('l\'admin crée un client sans mot de passe : email de choix du mot de passe envoyé', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'account_type' => 'individual', 'first_name' => 'Léa', 'last_name' => 'Martin',
            'phone' => '0611223344', 'email' => 'lea@example.com',
        ])
        ->call('create')->assertHasNoFormErrors();

    $user = User::query()->where('email', 'lea@example.com')->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->account_type)->toBe(AccountType::Individual)
        ->and(Hash::check('', $user->password))->toBeFalse();
    Mail::assertQueued(AccountCreatedByAdminMail::class, fn ($m) => $m->hasTo('lea@example.com') && $m->token !== '');
});

test('un pro créé par l\'admin est validé d\'office, SIRET obligatoire', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(CreateUser::class)
        ->fillForm(['account_type' => 'pro', 'first_name' => 'A', 'last_name' => 'B', 'phone' => '0611223344', 'email' => 'pro@example.com'])
        ->call('create')->assertHasFormErrors(['company_name', 'siret']);

    Livewire::test(CreateUser::class)
        ->fillForm(['account_type' => 'pro', 'first_name' => 'A', 'last_name' => 'B', 'phone' => '0611223344', 'email' => 'pro@example.com',
            'company_name' => 'Pro SARL', 'siret' => '732 829 320 00074'])
        ->call('create')->assertHasNoFormErrors();

    $user = User::query()->where('email', 'pro@example.com')->firstOrFail();
    expect($user->isApprovedPro())->toBeTrue()->and($user->siret)->toBe('73282932000074');
});

test('la création manuelle refuse un email déjà utilisé', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    User::factory()->create(['email' => 'dup@example.com']);

    Livewire::test(CreateUser::class)
        ->fillForm(['account_type' => 'individual', 'first_name' => 'A', 'last_name' => 'B', 'phone' => '0611223344', 'email' => 'dup@example.com'])
        ->call('create')->assertHasFormErrors(['email']);
    Mail::assertNothingQueued();
});

test('l\'email n\'est pas modifiable en édition', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $user = User::factory()->create(['email' => 'avant@example.com']);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['first_name' => 'Nouveau', 'email' => 'apres@example.com'])->call('save');

    $user->refresh();
    expect($user->first_name)->toBe('Nouveau')->and($user->email)->toBe('avant@example.com');
});

// --- Droits -----------------------------------------------------------------

test('un client ne peut pas accéder à la gestion des clients de l\'admin', function () {
    $this->actingAs(User::factory()->create(), 'web');

    $this->get('/admin/users')->assertRedirect('/admin/login');
    $this->get('/admin/users/create')->assertRedirect('/admin/login');
});

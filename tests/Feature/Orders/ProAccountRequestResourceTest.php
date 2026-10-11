<?php

declare(strict_types=1);

use App\Actions\Pro\ProcessProAccountRequestAction;
use App\Enums\ProRequestStatus;
use App\Filament\Resources\ProAccountRequestResource;
use App\Filament\Resources\ProAccountRequestResource\Pages\ListProAccountRequests;
use App\Filament\Resources\ProAccountRequestResource\Pages\ViewProAccountRequest;
use App\Mail\AccountCreatedByAdminMail;
use App\Mail\ProAccountRequestApprovedMail;
use App\Mail\ProAccountRequestRejectedMail;
use App\Models\Admin;
use App\Models\ProAccountRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
});

test('l\'admin voit la liste et peut filtrer par statut', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $pending = ProAccountRequest::factory()->create();
    $rejected = ProAccountRequest::factory()->create(['status' => ProRequestStatus::Rejected]);

    Livewire::test(ListProAccountRequests::class)
        ->assertCanSeeTableRecords([$pending, $rejected])
        ->filterTable('status', 'pending')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$rejected]);
});

test('la fiche détail s\'affiche', function () {
    $admin = Admin::factory()->create();
    $request = ProAccountRequest::factory()->create(['company_name' => 'Burger Exemple']);

    $this->actingAs($admin, 'admin')->get('/admin/pro-account-requests/'.$request->id)
        ->assertOk()->assertSee('Burger Exemple');
});

test('Approuver change le statut, enregistre le commentaire et envoie l\'email', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $request = ProAccountRequest::factory()->create(['email' => 'pro@example.test']);

    Livewire::test(ViewProAccountRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('approve', ['admin_comment' => 'À bientôt'])
        ->assertHasNoActionErrors();

    $request->refresh();
    expect($request->status)->toBe(ProRequestStatus::Approved)
        ->and($request->admin_comment)->toBe('À bientôt')
        ->and($request->processed_at)->not->toBeNull();
    Mail::assertQueued(ProAccountRequestApprovedMail::class, fn ($m) => $m->hasTo('pro@example.test'));
});

test('Refuser change le statut et envoie l\'email de refus', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $request = ProAccountRequest::factory()->create(['email' => 'pro@example.test']);

    Livewire::test(ViewProAccountRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('reject')
        ->assertHasNoActionErrors();

    expect($request->refresh()->status)->toBe(ProRequestStatus::Rejected)
        ->and($request->admin_comment)->toBeNull();
    Mail::assertQueued(ProAccountRequestRejectedMail::class, fn ($m) => $m->hasTo('pro@example.test'));
});

test('une demande déjà traitée ne peut pas l\'être deux fois (pas de second email)', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $request = ProAccountRequest::factory()->create(['status' => ProRequestStatus::Approved]);

    Livewire::test(ViewProAccountRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionHidden('approve')
        ->assertActionHidden('reject');

    expect(fn () => app(ProcessProAccountRequestAction::class)->reject($request))
        ->toThrow(RuntimeException::class);
    Mail::assertNothingQueued();
    expect($request->refresh()->status)->toBe(ProRequestStatus::Approved);
});

test('le BO des demandes pro est fermé aux visiteurs et aux clients', function () {
    $request = ProAccountRequest::factory()->create();

    $this->get('/admin/pro-account-requests')->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->create(), 'web')->get('/admin/pro-account-requests')->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->create(), 'web')->get('/admin/pro-account-requests/'.$request->id)->assertRedirect('/admin/login');
});

test('la création depuis le BO n\'existe pas', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    expect(ProAccountRequestResource::canCreate())->toBeFalse();
});

test('approuver crée le compte client pro validé, rattaché à la demande, avec l\'email pour choisir le mot de passe', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $request = ProAccountRequest::factory()->create([
        'email' => 'nouveau-pro@example.test', 'contact_first_name' => 'Paul', 'contact_last_name' => 'Boulanger',
        'phone' => '0611223344', 'company_name' => 'Pizzeria du Port', 'siret' => '73282932000074',
    ]);

    Livewire::test(ViewProAccountRequest::class, ['record' => $request->getRouteKey()])
        ->callAction('approve')
        ->assertHasNoActionErrors();

    $user = User::query()->where('email', 'nouveau-pro@example.test')->sole();
    expect($user->isApprovedPro())->toBeTrue()
        ->and($user->company_name)->toBe('Pizzeria du Port')
        ->and($user->siret)->toBe('73282932000074')
        ->and($user->first_name)->toBe('Paul')
        ->and($request->fresh()->user_id)->toBe($user->id);

    Mail::assertQueued(AccountCreatedByAdminMail::class, fn ($m) => $m->hasTo('nouveau-pro@example.test'));
    Mail::assertQueued(ProAccountRequestApprovedMail::class, fn ($m) => $m->accountCreated && str_contains($m->render(), 'second email'));
});

test('approuver avec l\'email d\'un client existant le passe en pro sans créer de doublon', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $existing = User::factory()->create(['email' => 'deja-client@example.test']);
    $request = ProAccountRequest::factory()->create(['email' => 'deja-client@example.test', 'company_name' => 'Traiteur Martin']);

    app(ProcessProAccountRequestAction::class)->approve($request);

    expect(User::query()->where('email', 'deja-client@example.test')->count())->toBe(1)
        ->and($existing->fresh()->isApprovedPro())->toBeTrue()
        ->and($existing->fresh()->company_name)->toBe('Traiteur Martin')
        ->and($request->fresh()->user_id)->toBe($existing->id);

    Mail::assertNotQueued(AccountCreatedByAdminMail::class);
    Mail::assertQueued(ProAccountRequestApprovedMail::class, fn ($m) => ! $m->accountCreated && str_contains($m->render(), 'Me connecter'));
});

test('rattrapage : une demande approuvée sans compte propose « Créer le compte client », une seule fois', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
    $request = ProAccountRequest::factory()->create(['email' => 'avant-correctif@example.test']);
    $request->forceFill(['status' => ProRequestStatus::Approved, 'processed_at' => now()])->save();

    Livewire::test(ViewProAccountRequest::class, ['record' => $request->getRouteKey()])
        ->assertActionVisible('createAccount')
        ->callAction('createAccount')
        ->assertHasNoActionErrors();

    expect($request->fresh()->user_id)->not->toBeNull();

    Livewire::test(ViewProAccountRequest::class, ['record' => $request->getRouteKey()])->assertActionHidden('createAccount');
    expect(fn () => app(ProcessProAccountRequestAction::class)->createAccountFor($request->fresh()))->toThrow(RuntimeException::class);
});

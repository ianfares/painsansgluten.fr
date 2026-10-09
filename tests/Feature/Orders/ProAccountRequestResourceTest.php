<?php

declare(strict_types=1);

use App\Actions\Pro\ProcessProAccountRequestAction;
use App\Enums\ProRequestStatus;
use App\Filament\Resources\ProAccountRequestResource;
use App\Filament\Resources\ProAccountRequestResource\Pages\ListProAccountRequests;
use App\Filament\Resources\ProAccountRequestResource\Pages\ViewProAccountRequest;
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

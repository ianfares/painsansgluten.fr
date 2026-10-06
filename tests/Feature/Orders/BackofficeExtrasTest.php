<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Pages\Expeditions;
use App\Filament\Resources\AdminResource\Pages\ListAdmins;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\RelationManagers\OrdersRelationManager;
use App\Filament\Widgets\OrdersStatsWidget;
use App\Models\Address;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
});

test('la vue expéditions ne liste que les commandes payées ou en préparation', function () {
    $toShow = Order::factory()->create(['status' => OrderStatus::Preparing]);
    $hidden = Order::factory()->create(['status' => OrderStatus::Delivered]);

    Livewire::test(Expeditions::class)
        ->assertCanSeeTableRecords([$toShow])
        ->assertCanNotSeeTableRecords([$hidden]);
});

test('la liste des clients affiche un client et jamais son mot de passe', function () {
    $user = User::factory()->create();

    $html = Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$user])
        ->html();

    expect($html)->not->toContain($user->password);
});

test('la fiche client affiche ses adresses', function () {
    $user = User::factory()->create();
    Address::factory()->for($user)->create(['line1' => '5 rue des Tanneurs']);

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->assertSee('5 rue des Tanneurs');
});

test('la fiche client liste ses commandes', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    Livewire::test(OrdersRelationManager::class, [
        'ownerRecord' => $user,
        'pageClass' => ViewUser::class,
    ])->assertCanSeeTableRecords([$order]);
});

test('un admin ne peut pas se supprimer lui-même', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    Livewire::test(ListAdmins::class)->callTableAction('delete', $admin);

    expect(Admin::query()->whereKey($admin->id)->exists())->toBeTrue();
});

test('un admin peut supprimer un autre compte admin', function () {
    $admin = Admin::factory()->create();
    $other = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    Livewire::test(ListAdmins::class)->callTableAction('delete', $other);

    expect(Admin::query()->whereKey($other->id)->exists())->toBeFalse();
});

test('le widget de statistiques calcule le CA et les commandes du mois', function () {
    Order::factory()->create([
        'status' => OrderStatus::Paid,
        'paid_at' => now(),
        'total_ttc' => 5000,
    ]);
    Order::factory()->create([
        'status' => OrderStatus::PendingPayment,
        'payment_method' => PaymentMethod::BankTransfer,
    ]);

    $widget = new OrdersStatsWidget;
    $stats = (fn () => $this->getStats())->call($widget);

    expect($stats[0]->getValue())->toBe('50,00 €')
        ->and((int) $stats[1]->getValue())->toBeGreaterThanOrEqual(2)
        ->and((int) $stats[2]->getValue())->toBe(1);
});

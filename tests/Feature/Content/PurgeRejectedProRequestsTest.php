<?php

declare(strict_types=1);

use App\Enums\ProRequestStatus;
use App\Models\ProAccountRequest;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 10)->setTime(12, 0));
});

function proRequestProcessed(ProRequestStatus $status, CarbonInterface $processedAt): ProAccountRequest
{
    $request = ProAccountRequest::factory()->create();
    $request->forceFill(['status' => $status, 'processed_at' => $processedAt])->save();

    return $request;
}

test('une demande refusée depuis 3 mois et 1 jour est supprimée', function () {
    $old = proRequestProcessed(ProRequestStatus::Rejected, now()->subMonths(3)->subDay());

    $this->artisan('pro-requests:purge-rejected')->assertSuccessful();

    expect(ProAccountRequest::query()->whereKey($old->id)->exists())->toBeFalse();
});

test('une demande refusée depuis 2 mois est conservée', function () {
    $recent = proRequestProcessed(ProRequestStatus::Rejected, now()->subMonths(2));

    $this->artisan('pro-requests:purge-rejected')->assertSuccessful();

    expect(ProAccountRequest::query()->whereKey($recent->id)->exists())->toBeTrue();
});

test('les demandes en attente ou acceptées, même anciennes, ne sont jamais supprimées', function () {
    $pending = ProAccountRequest::factory()->create(['created_at' => now()->subYear()]);
    $approved = proRequestProcessed(ProRequestStatus::Approved, now()->subYear());

    $this->artisan('pro-requests:purge-rejected')->assertSuccessful();

    expect(ProAccountRequest::query()->whereKey([$pending->id, $approved->id])->count())->toBe(2);
});

test('seul le nombre de demandes supprimées est journalisé, sans donnée personnelle', function () {
    $old = proRequestProcessed(ProRequestStatus::Rejected, now()->subMonths(4));
    Log::spy();

    $this->artisan('pro-requests:purge-rejected')->assertSuccessful();

    Log::shouldHaveReceived('info')->once()->withArgs(
        fn (string $message): bool => str_contains($message, '1 demande(s)') && ! str_contains($message, $old->email),
    );
});

test('la purge est planifiée chaque jour', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $e): bool => str_contains($e->command, 'pro-requests:purge-rejected'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 0 * * *');
});

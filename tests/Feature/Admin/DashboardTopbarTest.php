<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Support\Carbon;

test('admin : plus d\'encadré Filament (documentation, GitHub), date et heure de Paris dans la barre du haut', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-13 08:45', 'UTC')); // 10:45 à Paris

    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/'.config('admin.path'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('filamentphp.com/docs')
        ->and($html)->not->toContain('github.com/filamentphp')
        ->and($html)->toContain('mardi 13 octobre 2026 · 10:45');
});

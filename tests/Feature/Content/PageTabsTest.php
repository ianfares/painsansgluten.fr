<?php

declare(strict_types=1);

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Models\Admin;
use App\Models\Page;
use App\Models\PickupPoint;
use Livewire\Livewire;

/**
 * Onglets de page (24/10/2026, « Où nous trouver ») : titre + texte libre,
 * et liste automatique des points de retrait actifs sur demande.
 */
function findUsPage(array $tabs): Page
{
    return Page::factory()->create(['is_published' => true, 'title' => 'Où nous trouver', 'slug' => 'ou-nous-trouver', 'content' => '<p>Intro commune</p>', 'tabs' => $tabs]);
}

test('les onglets s\'affichent avec leur texte ; la liste des points de retrait seulement si demandée', function () {
    PickupPoint::factory()->create(['name' => 'Épicerie de Granville', 'is_active' => true, 'pickup_weekdays' => ['friday'], 'pickup_from' => '09:00', 'pickup_until' => '12:30']);
    PickupPoint::factory()->create(['name' => 'Boutique fermée', 'is_active' => false]);

    $page = findUsPage([
        ['title' => 'Nos points de retrait', 'content' => '<p>Retirez vos commandes chez nos partenaires.</p>', 'show_pickup_points' => true],
        ['title' => 'Nos marchés', 'content' => '<p>Marché d\'Avranches le samedi.</p>', 'show_pickup_points' => false],
        ['title' => 'Nos revendeurs', 'content' => '<p>Épiceries fines.</p>'],
    ]);

    $this->get(route('content.show', $page))->assertOk()
        ->assertSee('Intro commune')
        ->assertSeeInOrder(['Nos points de retrait', 'Nos marchés', 'Nos revendeurs'])
        ->assertSee('id="panel-nos-marches"', false)
        ->assertSee('Marché d\'Avranches le samedi.', false)
        ->assertSee('Épicerie de Granville')
        ->assertSee('Retrait le vendredi, de 9 h à 12 h 30')
        ->assertDontSee('Boutique fermée');
});

test('sans onglet « points de retrait », aucune liste ; une page sans onglets reste inchangée', function () {
    PickupPoint::factory()->create(['name' => 'Épicerie de Granville', 'is_active' => true]);

    $this->get(route('content.show', findUsPage([['title' => 'Nos marchés', 'content' => '<p>Samedi.</p>']])))
        ->assertOk()->assertDontSee('Épicerie de Granville');

    $plain = Page::factory()->create(['is_published' => true, 'slug' => 'mentions-legales', 'content' => '<p>Éditeur du site</p>', 'tabs' => null]);
    $this->get(route('content.show', $plain))->assertOk()->assertSee('Éditeur du site', false)->assertDontSee('role="tablist"', false);
});

test('le texte des onglets est purifié à l\'enregistrement', function () {
    $page = findUsPage([['title' => 'Nos marchés', 'content' => '<p>Samedi</p><script>alert(1)</script>', 'show_pickup_points' => false]]);

    expect($page->fresh()->tabs[0]['content'])->not->toContain('<script>')->toContain('Samedi');
});

test('jours de retrait en clair', function () {
    expect(PickupPoint::factory()->make(['pickup_weekdays' => ['friday']])->pickupDaysLabel())->toBe('le vendredi')
        ->and(PickupPoint::factory()->make(['pickup_weekdays' => ['friday', 'tuesday']])->pickupDaysLabel())->toBe('les mardi et vendredi')
        ->and(PickupPoint::factory()->make(['pickup_weekdays' => null])->pickupDaysLabel())->toBeNull();
});

test('admin : création d\'une page avec onglets', function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Où nous trouver', 'slug' => 'ou-nous-trouver', 'is_published' => true,
            'tabs' => [
                ['title' => 'Nos points de retrait', 'content' => '<p>Partenaires</p>', 'show_pickup_points' => true],
                ['title' => 'Nos marchés', 'content' => '<p>Samedi</p>', 'show_pickup_points' => false],
            ],
        ])
        ->call('create')->assertHasNoFormErrors();

    $tabs = Page::query()->where('slug', 'ou-nous-trouver')->sole()->tabs;
    expect($tabs)->toHaveCount(2)
        ->and($tabs[0]['title'])->toBe('Nos points de retrait')
        ->and($tabs[0]['show_pickup_points'])->toBeTrue()
        ->and($tabs[1]['title'])->toBe('Nos marchés');
});

test('chaque onglet n\'affiche que les points choisis (vide = tous)', function () {
    $shop = PickupPoint::factory()->create(['name' => 'Épicerie de Granville', 'is_active' => true]);
    $market = PickupPoint::factory()->create(['name' => 'Marché de Villedieu', 'is_active' => true]);

    $page = findUsPage([
        ['title' => 'Nos points de retrait', 'content' => null, 'show_pickup_points' => true, 'pickup_point_ids' => [$shop->id]],
        ['title' => 'Nos marchés', 'content' => null, 'show_pickup_points' => true, 'pickup_point_ids' => [(string) $market->id]],
        ['title' => 'Tous', 'content' => null, 'show_pickup_points' => true],
    ]);

    expect($page->fresh()->tabs[1]['pickup_point_ids'])->toBe([$market->id]);

    $html = $this->get(route('content.show', $page))->assertOk()->getContent();
    $panel = fn (string $id): string => str($html)->after('id="panel-'.$id.'"')->before('</section>')->toString();

    expect($panel('nos-points-de-retrait'))->toContain('Épicerie de Granville')->not->toContain('Marché de Villedieu')
        ->and($panel('nos-marches'))->toContain('Marché de Villedieu')->not->toContain('Épicerie de Granville')
        ->and($panel('tous'))->toContain('Épicerie de Granville')->toContain('Marché de Villedieu');
});

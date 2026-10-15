<?php

declare(strict_types=1);

use App\Filament\Pages\ManageHomepageSettings;
use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Admin;
use App\Models\Category;
use App\Settings\HomepageSettings;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
});

test('la liste des catégories est accessible', function () {
    Category::factory()->count(3)->create();

    Livewire::test(ListCategories::class)->assertOk();
});

test('on peut créer une catégorie avec un slug unique', function () {
    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Pains',
            'slug' => 'pains-sans-gluten',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::query()->where('slug', 'pains-sans-gluten')->exists())->toBeTrue();
});

test('deux catégories ne peuvent pas partager le même slug', function () {
    Category::factory()->create(['slug' => 'pains-sans-gluten']);

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => 'Autre', 'slug' => 'pains-sans-gluten'])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

test('le texte de présentation accepte un long texte (plus de 500 caractères)', function () {
    $long = str_repeat('Pains sans gluten fabriqués à la commande. ', 60); // ~2 600 caractères

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => 'Pains', 'slug' => 'pains', 'description' => $long])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Category::query()->where('slug', 'pains')->value('description'))->toBe($long);
});

test('paramètres de l\'accueil : le texte de présentation s\'édite avec l\'éditeur de mise en forme', function () {
    Livewire::test(ManageHomepageSettings::class)
        ->assertOk()
        ->fillForm(['presentation_text' => '<p>Texte <strong>gras</strong></p>'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(HomepageSettings::class)->presentation_text)->toContain('<strong>gras</strong>');
});

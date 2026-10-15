<?php

declare(strict_types=1);

use App\Models\Category;

test('le pictogramme de repli correspond au type de catégorie', function (string $name, string $expected) {
    $category = Category::factory()->make(['name' => $name]);

    expect($category->fallbackIcon())->toBe($expected);
})->with([
    ['Pains sans gluten', '🥖'],
    ['Viennoiseries', '🥐'],
    ['Pâtisseries', '🍰'],
    ['Biscuits', '🍪'],
    ['Apéritif ', '🫒'],
    ['Epicerie', '🫙'],
    ['Épicerie fine', '🫙'],
    ['Catégorie quelconque', '🌾'],
]);

test('l\'icône choisie dans le back-office remplace l\'icône devinée', function () {
    expect(Category::factory()->make(['name' => 'Apéritif', 'icon' => '🧀'])->icon())->toBe('🧀')
        ->and(Category::factory()->make(['name' => 'Apéritif', 'icon' => '  '])->icon())->toBe('🫒');
});

<?php

declare(strict_types=1);

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(Admin::factory()->create(), 'admin');
    $this->category = Category::factory()->create();
});

test('on peut créer un produit en brouillon sans champs obligatoires-pour-publier', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm([
            'category_id' => $this->category->id,
            'name' => 'Le Mie\'miam',
            'slug' => 'mie-miam',
            'reference' => 'REF-001',
            'price_ttc' => '5.40',
            'vat_rate' => '5.5',
            'is_published' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('slug', 'mie-miam')->sole();
    expect($product->price_ttc)->toBe(540)
        ->and($product->is_published)->toBeFalse();
});

test('la publication est bloquée tant que les champs obligatoires-pour-publier manquent', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm([
            'category_id' => $this->category->id,
            'name' => 'Le Mie\'miam',
            'slug' => 'mie-miam',
            'reference' => 'REF-001',
            'price_ttc' => '5.40',
            'vat_rate' => '5.5',
            'is_published' => true,
            // ingrédients, poids, image principale volontairement absents
        ])
        ->call('create')
        ->assertHasFormErrors(['ingredients', 'net_weight_g', 'shipping_weight_g', 'main']);
});

test('un produit complet peut être publié et génère une conversion WebP de l\'image principale', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm([
            'category_id' => $this->category->id,
            'name' => 'Le Mie\'miam',
            'slug' => 'mie-miam',
            'reference' => 'REF-001',
            'price_ttc' => '5.40',
            'vat_rate' => '5.5',
            'is_published' => true,
            'ingredients' => 'Farine de riz, eau, levure.',
            'net_weight_g' => 350,
            'shipping_weight_g' => 380,
            'main' => [UploadedFile::fake()->image('mie-miam.jpg', 800, 800)],
            'main_alt' => 'Le Mie\'miam sans gluten',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('slug', 'mie-miam')->sole();
    $media = $product->getFirstMedia('main');

    expect($media)->not->toBeNull()
        ->and($media->hasGeneratedConversion('thumbnail'))->toBeTrue()
        ->and($media->hasGeneratedConversion('fiche'))->toBeTrue()
        ->and($media->getPath('thumbnail'))->toEndWith('.webp');
});

test('dupliquer un produit copie tout sauf slug/référence, et le désactive', function () {
    $product = Product::factory()->for($this->category)->create([
        'slug' => 'original',
        'reference' => 'REF-ORIG',
        'is_available' => true,
        'is_published' => true,
    ]);

    $copy = $product->replicate(['slug', 'reference']);
    $copy->name = $product->name.' (copie)';
    $copy->slug = $product->slug.'-copie-test';
    $copy->reference = $product->reference.'-COPIE-TEST';
    $copy->is_available = false;
    $copy->is_published = false;
    $copy->save();

    expect($copy->slug)->not->toBe($product->slug)
        ->and($copy->reference)->not->toBe($product->reference)
        ->and($copy->is_available)->toBeFalse()
        ->and($copy->is_published)->toBeFalse()
        ->and($copy->short_description)->toBe($product->short_description);
});

test('un produit déjà commandé ne peut pas être supprimé', function () {
    $product = Product::factory()->for($this->category)->create();
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->for($product)->create();

    expect($product->hasBeenOrdered())->toBeTrue();
});

test('un produit jamais commandé peut être supprimé', function () {
    $product = Product::factory()->for($this->category)->create();

    expect($product->hasBeenOrdered())->toBeFalse();
});

test('les textes alternatifs de la galerie sont enregistrés sur chaque média', function () {
    $product = Product::factory()->for($this->category)->create(['is_published' => false]);
    $product->addMedia(UploadedFile::fake()->image('vue-1.jpg'))->toMediaCollection('gallery');
    $media = $product->getMedia('gallery')->first();

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['galleryAltTexts' => [['media_id' => $media->id, 'alt' => 'Vue de face du Mie\'miam']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($media->fresh()->getCustomProperty('alt'))->toBe('Vue de face du Mie\'miam');
});

test('les valeurs nutritionnelles acceptent deux décimales (ex. sel 0,34 g)', function () {
    $product = Product::factory()->create(['category_id' => $this->category->id, 'is_published' => false]);

    // Sans step="any", le navigateur bloque l'envoi du formulaire en silence
    // (champ dans un onglet masqué) dès qu'on saisit plus d'une décimale.
    $this->get(EditProduct::getUrl(['record' => $product]))
        ->assertOk()
        ->assertSee('id="data.nutrition.salt"', false)
        ->assertDontSee('step="0.1"', false);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['nutrition' => ['salt' => '0.34']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((string) $product->fresh()->nutrition['salt'])->toBe('0.34');
});

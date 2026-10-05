<?php

declare(strict_types=1);

use App\Http\Controllers\Catalog\BoutiqueController;
use App\Http\Controllers\Catalog\HomeController;
use App\Http\Controllers\Catalog\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/panier', function () {
    return view('catalog.panier');
})->name('panier');

// Catalogue public (PLAN.md §5, §23 : slugs de catégories repris au niveau
// racine pour matcher les redirections 301 Shopify déjà seedées en T02).
Route::get('/boutique', [BoutiqueController::class, 'index'])->name('boutique.index');
Route::get('/produit/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/{category:slug}', [BoutiqueController::class, 'category'])
    ->name('categories.show')
    // Liste d'exclusion à maintenir : toute nouvelle route racine doit y être ajoutée
    // (voir docs/DECISIONS.md, T07).
    ->where('category', '(?!boutique|produit|panier|mon-compte|connexion|inscription|admin).*');

// Stub de l'espace client (PLAN.md §13) : le tableau de bord complet est
// construit en T18. Ce stub sert uniquement de cible de redirection pour
// Fortify (connexion, inscription, vérification d'email) et de preuve que
// le parcours d'authentification fonctionne de bout en bout (T03).
Route::middleware(['auth', 'verified'])->get('/mon-compte', function () {
    return view('compte.stub');
})->name('compte.dashboard');

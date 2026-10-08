<?php

declare(strict_types=1);

use App\Http\Controllers\Catalog\BoutiqueController;
use App\Http\Controllers\Catalog\HomeController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Compte\AccountController;
use App\Http\Controllers\Compte\InvoiceDownloadController;
use App\Http\Controllers\Content\FaqController;
use App\Http\Controllers\Content\SlugController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Accessible sans connexion via URL signée (invité) ou avec la policy (client propriétaire).
Route::get('/factures/{invoice}/telecharger', InvoiceDownloadController::class)->name('invoices.download');

Route::get('/panier', function () {
    return view('catalog.panier');
})->name('panier');

// Catalogue public (PLAN.md §5, §23 : slugs de catégories repris au niveau
// racine pour matcher les redirections 301 Shopify déjà seedées en T02).
Route::get('/boutique', [BoutiqueController::class, 'index'])->name('boutique.index');
Route::get('/produit/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/faq', FaqController::class)->name('faq');

// Tunnel de commande (PLAN.md §9.1, T13). Pas de middleware `auth` : le
// tunnel doit rester accessible en invité. `{order:token}` lie le
// paramètre au token aléatoire de la commande (jamais son id, pour éviter
// toute énumération — voir docs/DECISIONS.md, T13).
Route::get('/commande', [CheckoutController::class, 'create'])->name('checkout');
Route::get('/commande/stripe/{order:token}', [CheckoutController::class, 'stripeStart'])->name('checkout.stripe.start');
Route::get('/commande/confirmation/{order:token}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');

// Résolveur générique catégorie/page (PLAN.md §5, §16.1, §23 : T20) : les
// deux partagent le même niveau d'URL racine pour matcher les
// redirections 301 Shopify déjà seedées en T02.
Route::get('/{slug}', [SlugController::class, 'show'])
    ->name('content.show')
    // Liste d'exclusion à maintenir : toute nouvelle route racine doit y être ajoutée
    // (voir docs/DECISIONS.md, T07/T20).
    // Exclusion ancrée sur le segment entier : `faq-livraison` ou
    // `commandes-speciales` restent des slugs valides.
    ->where('slug', '(?!(?:boutique|produit|panier|faq|factures|mon-compte|connexion|inscription|admin|commande)(?:/|$)).*');

// Espace client (PLAN.md §13, T18). Email/mot de passe : routes Fortify
// déjà en place (T03, `user-profile-information.update`, `user-password.update`).
Route::middleware(['auth', 'verified'])->prefix('mon-compte')->name('compte.')->group(function () {
    Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/commandes', [AccountController::class, 'orders'])->name('orders');
    Route::get('/factures', [AccountController::class, 'invoices'])->name('invoices');
    Route::get('/informations', [AccountController::class, 'informations'])->name('informations');
    Route::put('/adresse', [AccountController::class, 'updateAddress'])->name('address.update');
    Route::post('/supprimer', [AccountController::class, 'requestDeletion'])->name('deletion.request');
});

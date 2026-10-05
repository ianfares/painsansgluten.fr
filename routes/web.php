<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Stub de l'espace client (PLAN.md §13) : le tableau de bord complet est
// construit en T18. Ce stub sert uniquement de cible de redirection pour
// Fortify (connexion, inscription, vérification d'email) et de preuve que
// le parcours d'authentification fonctionne de bout en bout (T03).
Route::middleware(['auth', 'verified'])->get('/mon-compte', function () {
    return view('compte.stub');
})->name('compte.dashboard');

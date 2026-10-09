<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreProAccountRequest;
use App\Mail\Admin\NewProAccountRequestMail;
use App\Mail\ProAccountRequestReceivedMail;
use App\Models\ProAccountRequest;
use App\Services\Mail\AdminMailer;
use App\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Mews\Purifier\Facades\Purifier;

/**
 * Page « Professionnels » : texte de présentation (réglage BO) + formulaire
 * de demande de compte pro (T26 B1). Anti-spam : Turnstile + 3 demandes
 * acceptées par heure et par IP. Aucune donnée personnelle dans les logs.
 */
class ProAccountRequestController extends Controller
{
    private const MAX_REQUESTS_PER_HOUR = 3;

    private const WINDOW_SECONDS = 3600;

    public function create(ShopSettings $shop): View
    {
        return view('content.pro-request', [
            // Texte riche saisi en BO : purifié (liste blanche) avant affichage (QUALITE.md §2.3).
            'introHtml' => Purifier::clean((string) $shop->pro_intro_html),
            'activityTypes' => $shop->pro_activity_types,
            'productOptions' => ProAccountRequest::productOptions(),
        ]);
    }

    public function store(StoreProAccountRequest $request): RedirectResponse
    {
        $key = 'pro-request:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_REQUESTS_PER_HOUR)) {
            return back()->withInput()->with('pro_error', 'Vous avez déjà envoyé plusieurs demandes récemment. Merci de réessayer dans une heure ou de nous contacter directement.');
        }

        $proRequest = ProAccountRequest::query()->create([
            ...$request->safe()->except(['consent', 'cf-turnstile-response']),
            'products_of_interest' => $request->validated('products_of_interest') ?? [],
            'consent_at' => now(),
        ]);

        RateLimiter::hit($key, self::WINDOW_SECONDS);

        Mail::to($proRequest->email)->queue(new ProAccountRequestReceivedMail($proRequest));
        AdminMailer::queue(new NewProAccountRequestMail($proRequest));

        return redirect()->route('pro.request.create')->with('pro_sent', true);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageMail;
use App\Models\Page;
use App\Rules\Turnstile;
use App\Settings\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Page Contact : texte éditable en BO (page « contact », si publiée) +
 * formulaire protégé par Cloudflare Turnstile (décision du 2026-10-09,
 * voir docs/DECISIONS.md).
 */
class ContactController extends Controller
{
    public function show(): View
    {
        return view('content.contact', [
            'page' => Page::query()->where('slug', 'contact')->where('is_published', true)->first(),
        ]);
    }

    public function store(Request $request, ShopSettings $shop): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'cf-turnstile-response' => ['required', new Turnstile],
        ], [
            'name.required' => 'Merci d\'indiquer votre nom.',
            'email.required' => 'Merci d\'indiquer votre adresse email.',
            'email.email' => 'Cette adresse email n\'est pas valide.',
            'message.required' => 'Merci d\'écrire votre message.',
            'message.min' => 'Votre message est un peu court (10 caractères minimum).',
            'message.max' => 'Votre message est trop long (5 000 caractères maximum).',
            'cf-turnstile-response.required' => 'Merci de valider la vérification anti-robot.',
        ]);

        $to = $shop->contact_email ?: $shop->admin_notification_email;
        if (! $to) {
            Log::error('Contact : aucune adresse de réception paramétrée (contact_email / admin_notification_email).');

            return back()->withInput()->with('contact_error', 'Le formulaire est momentanément indisponible. Merci de réessayer plus tard.');
        }

        Mail::to($to)->queue(new ContactMessageMail([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'],
        ]));

        return redirect()->route('contact')->with('contact_sent', true);
    }
}

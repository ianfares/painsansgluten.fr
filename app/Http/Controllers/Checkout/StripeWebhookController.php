<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Actions\Payments\HandleStripeWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * `POST /webhooks/stripe` (T14) : hors CSRF, signature vérifiée avec
 * STRIPE_WEBHOOK_SECRET. Un message non signé par Stripe est refusé (400).
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, HandleStripeWebhookAction $action): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');

        // Secret absent ou resté à la valeur factice de .env.example (publique) :
        // n'importe qui pourrait signer un faux paiement, on refuse tout.
        if ($secret === '' || str_contains($secret, 'changeme')) {
            Log::critical('Stripe : STRIPE_WEBHOOK_SECRET non configuré, webhook refusé.');

            return response('Webhook non configuré.', 503);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('Signature invalide.', 400);
        }

        $action->execute($event);

        return response('OK', 200);
    }
}

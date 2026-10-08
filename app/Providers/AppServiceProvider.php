<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderRefunded;
use App\Listeners\Orders\GenerateCreditNoteOnOrderRefunded;
use App\Listeners\Orders\GenerateInvoiceOnOrderPaid;
use App\Models\User;
use App\Services\Cart\CartService;
use App\View\Composers\CartCountComposer;
use App\View\Composers\FooterComposer;
use App\View\Composers\HeaderComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StripeClient::class, fn () => new StripeClient((string) config('services.stripe.secret')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('components.site.header', HeaderComposer::class);
        View::composer('components.site.footer', FooterComposer::class);
        View::composer('components.layouts.app', CartCountComposer::class);

        // Fusion du panier invité dans celui du client à la connexion (PLAN.md §7).
        Event::listen(function (Login $event): void {
            if ($event->user instanceof User) {
                app(CartService::class)->mergeIntoUser($event->user);
            }
        });

        // Facturation (PLAN.md §12, T16).
        Event::listen(OrderPaid::class, GenerateInvoiceOnOrderPaid::class);
        Event::listen(OrderRefunded::class, GenerateCreditNoteOnOrderRefunded::class);
    }
}

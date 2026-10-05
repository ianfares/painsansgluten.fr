<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\Cart\CartService;
use App\View\Composers\CartCountComposer;
use App\View\Composers\FooterComposer;
use App\View\Composers\HeaderComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}

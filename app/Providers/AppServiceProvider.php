<?php

declare(strict_types=1);

namespace App\Providers;

use App\View\Composers\FooterComposer;
use App\View\Composers\HeaderComposer;
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
    }
}

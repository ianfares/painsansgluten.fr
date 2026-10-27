<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Settings\HomepageSettings;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(config('admin.path'))
            ->authGuard('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandLogo(fn () => ($path = app(HomepageSettings::class)->logo_path)
                ? Storage::disk('public')->url($path)
                : null)
            ->brandLogoHeight('3.5rem')
            ->favicon(fn () => ($path = app(HomepageSettings::class)->favicon_path)
                ? Storage::disk('public')->url($path)
                : null)
            // Date et heure de Paris dans la barre du haut (demande de Ian).
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.topbar-clock'))
            // Lien vers le rapport GA4 (27/10/2026, demande de Ian) : Google interdit
            // l'affichage de ses pages dans un cadre, d'où l'ouverture dans un onglet.
            ->navigationItems([
                NavigationItem::make('Rapport Google Analytics')
                    ->url('https://analytics.google.com/analytics/web/#/a62003154p558281421/reports/intelligenthome', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-chart-bar')
                    ->group('Google Analytics'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

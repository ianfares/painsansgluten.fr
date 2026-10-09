<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Pages\Revenue;
use App\Http\Controllers\Content\SeoFilesController;
use App\Models\Category;
use App\Models\FaqItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Settings\ShopSettings;
use App\View\Composers\CartCountComposer;
use App\View\Composers\FooterComposer;
use App\View\Composers\HeaderComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Stripe\StripeClient;
use Symfony\Component\Mime\Address;

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
        // Widgets de la page « Recettes » (voir App\Filament\Pages\Revenue::WIDGETS).
        foreach (Revenue::WIDGETS as $widget) {
            Livewire::component(Str::of($widget)->explode('\\')->map(fn (string $part) => Str::kebab($part))->implode('.'), $widget);
        }

        // Garde-fou large sur toutes les soumissions du formulaire pro (échecs de validation compris) ;
        // la limite métier de 3 demandes acceptées / heure / IP est dans ProAccountRequestController.
        RateLimiter::for('pro-request', fn (Request $request) => Limit::perHour(30)->by((string) $request->ip()));

        View::composer('components.site.header', HeaderComposer::class);
        View::composer('components.site.footer', FooterComposer::class);
        View::composer('components.layouts.app', CartCountComposer::class);

        // Fusion du panier invité dans celui du client à la connexion (PLAN.md §7).
        Event::listen(function (Login $event): void {
            if ($event->user instanceof User) {
                app(CartService::class)->mergeIntoUser($event->user);
            }
        });

        // Les écouteurs de app/Listeners (factures, avoirs, emails) sont
        // détectés automatiquement par Laravel : ne pas les redéclarer ici,
        // sinon ils s'exécutent deux fois (`php artisan event:list`).

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject('Bienvenue ! Confirmez votre adresse email')
            ->markdown('emails.auth.verify-email', ['url' => $url, 'name' => $notifiable->first_name ?? null]));

        ResetPassword::toMailUsing(fn (object $notifiable, string $token) => (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->markdown('emails.auth.reset-password', [
                'url' => url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false)),
                'minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]));

        // Plan du site et llms.txt régénérés dès qu'un contenu change (T21).
        foreach ([Product::class, Category::class, Page::class, FaqItem::class] as $model) {
            $model::saved(fn () => Cache::deleteMultiple(SeoFilesController::CACHE_KEYS));
            $model::deleted(fn () => Cache::deleteMultiple(SeoFilesController::CACHE_KEYS));
        }

        // Expéditeur et adresse de réponse paramétrables en BO (PLAN.md §15).
        Event::listen(function (MessageSending $event): void {
            $shop = app(ShopSettings::class);
            if ($shop->sender_email) {
                $event->message->from(new Address($shop->sender_email, (string) config('mail.from.name')));
            }
            if ($shop->reply_to_email && $event->message->getReplyTo() === []) {
                $event->message->replyTo($shop->reply_to_email);
            }
        });
    }
}

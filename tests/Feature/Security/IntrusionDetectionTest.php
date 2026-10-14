<?php

declare(strict_types=1);

use App\Mail\Admin\SecurityAlertMail;
use App\Models\Admin;
use App\Models\User;
use App\Settings\ShopSettings;
use Filament\Pages\Auth\Login;
use Illuminate\Auth\Events\Login as LoginEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    // Faux projet dans un dossier temporaire : jamais le vrai code.
    $this->root = sys_get_temp_dir().'/integrity-'.uniqid();
    File::ensureDirectoryExists($this->root.'/app');
    File::ensureDirectoryExists($this->root.'/storage/app/public/products');
    File::ensureDirectoryExists($this->root.'/public');
    File::put($this->root.'/app/Checkout.php', '<?php // paiement');
    File::put($this->root.'/public/index.php', '<?php // entrée');
    File::put($this->root.'/storage/app/public/products/pain.webp', 'image');

    config([
        'security.alert_email' => 'support@yafar.org',
        'security.integrity.root' => $this->root,
        'security.integrity.manifest' => $this->root.'/storage/app/private/security/manifest.json',
        'security.integrity.watched' => ['app', 'public', '.env'],
        'security.integrity.uploads' => ['storage/app'],
    ]);

    $shop = app(ShopSettings::class);
    $shop->shop_name = 'Mon Sans Gluten';
    $shop->save();
});

afterEach(fn () => File::deleteDirectory($this->root));

test('site intact après la référence : aucune alerte', function () {
    $this->artisan('security:integrity-snapshot')->assertSuccessful();
    $this->artisan('security:integrity-check')->assertSuccessful();

    Mail::assertNothingSent();
    expect(substr(sprintf('%o', fileperms(config('security.integrity.manifest'))), -4))->toBe('0600');
});

test('fichier modifié, ajouté ou supprimé : email à support@yafar.org, objet avec le nom du site et ALERTE, détail dans le corps', function () {
    $this->artisan('security:integrity-snapshot')->assertSuccessful();

    File::put($this->root.'/app/Checkout.php', '<?php // paiement + vol de cartes');
    File::put($this->root.'/public/shell.php', '<?php system($_GET["c"]);');
    File::delete($this->root.'/public/index.php');

    $this->artisan('security:integrity-check')->assertFailed();

    Mail::assertSent(SecurityAlertMail::class, function (SecurityAlertMail $mail) {
        $html = $mail->render();

        return $mail->hasTo('support@yafar.org')
            && $mail->envelope()->subject === 'Mon Sans Gluten — ALERTE — fichiers du site modifiés'
            && str_contains($html, 'Fichier(s) modifié(s) : 1') && str_contains($html, 'app/Checkout.php')
            && str_contains($html, 'Fichier(s) ajouté(s) : 1') && str_contains($html, 'public/shell.php')
            && str_contains($html, 'Fichier(s) supprimé(s) : 1') && str_contains($html, 'public/index.php');
    });
});

test('fichier PHP (ou .htaccess) déposé dans les uploads : alerte « fichier suspect », les images ne déclenchent rien', function () {
    $this->artisan('security:integrity-snapshot')->assertSuccessful();

    File::put($this->root.'/storage/app/public/products/photo.php', '<?php eval($_POST["x"]);');
    File::put($this->root.'/storage/app/public/products/.htaccess', 'AddType application/x-httpd-php .webp');
    File::put($this->root.'/storage/app/public/products/autre.webp', 'image');

    $this->artisan('security:integrity-check')->assertFailed();

    Mail::assertSent(SecurityAlertMail::class, fn (SecurityAlertMail $mail) => $mail->envelope()->subject === 'Mon Sans Gluten — ALERTE — fichier suspect détecté'
        && str_contains($mail->render(), 'storage/app/public/products/photo.php')
        && str_contains($mail->render(), 'storage/app/public/products/.htaccess')
        && ! str_contains($mail->render(), 'autre.webp'));
});

test('référence absente (supprimée par un pirate ?) : alerte', function () {
    $this->artisan('security:integrity-check')->assertFailed();

    Mail::assertSent(SecurityAlertMail::class, fn (SecurityAlertMail $mail) => str_contains($mail->render(), 'Référence introuvable'));
});

test('un lien symbolique (public/storage) n\'est jamais suivi', function () {
    symlink($this->root.'/storage/app/public', $this->root.'/public/storage');
    $this->artisan('security:integrity-snapshot')->assertSuccessful();

    File::put($this->root.'/storage/app/public/products/nouvelle.webp', 'image');

    $this->artisan('security:integrity-check')->assertSuccessful();
    Mail::assertNothingSent();
});

test('la vérification est planifiée toutes les heures', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('security:integrity-check')->assertSuccessful();
});

test('connexion admin par le formulaire : alerte à support@yafar.org avec le compte, l\'IP et le navigateur', function () {
    Admin::factory()->create(['name' => 'Angélique', 'email' => 'angelique@example.com', 'password' => 'secret-password-123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'angelique@example.com', 'password' => 'secret-password-123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $mail) => $mail->hasTo('support@yafar.org')
        && $mail->envelope()->subject === 'Mon Sans Gluten — ALERTE — connexion admin depuis une nouvelle adresse IP'
        && str_contains($mail->render(), 'Angélique (angelique@example.com)')
        && str_contains($mail->render(), 'Adresse IP'));
});

test('connexion admin : une alerte par nouvelle IP, aucune pour une IP connue ; IP jamais en clair en base ; client ignoré', function () {
    $admin = Admin::factory()->create();
    $loginFrom = function (string $ip, $user) {
        request()->server->set('REMOTE_ADDR', $ip);
        event(new LoginEvent('admin', $user, false));
    };

    $loginFrom('203.0.113.7', $admin);
    Mail::assertQueued(SecurityAlertMail::class, 1);
    Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $mail) => str_contains($mail->render(), '203.0.113.7'));

    $loginFrom('203.0.113.7', $admin);
    Mail::assertQueued(SecurityAlertMail::class, 1);

    $loginFrom('198.51.100.20', $admin);
    Mail::assertQueued(SecurityAlertMail::class, 2);

    $loginFrom('192.0.2.1', User::factory()->create());
    Mail::assertQueued(SecurityAlertMail::class, 2);

    expect(DB::table('admin_known_ips')->where('admin_id', $admin->id)->count())->toBe(2)
        ->and(DB::table('admin_known_ips')->pluck('ip_hash')->implode(','))->not->toContain('203.0.113.7');
});

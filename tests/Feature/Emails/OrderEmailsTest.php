<?php

declare(strict_types=1);

use App\Actions\Orders\ResendOrderConfirmationAction;
use App\Actions\Orders\ShipOrderAction;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\OrderRefunded;
use App\Events\Orders\OrderShipped;
use App\Mail\Admin\AccountDeletionRequestedMail;
use App\Mail\Admin\NewPaidOrderMail;
use App\Mail\BankTransferInstructionsMail;
use App\Mail\BankTransferPaidMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderRefundedMail;
use App\Mail\OrderShippedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Orders\OrderStateMachine;
use App\Settings\BillingSettings;
use App\Settings\ShippingSettings;
use App\Settings\ShopSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();

    $billing = app(BillingSettings::class);
    $billing->company_name = 'Mon Sans Gluten by Angélique';
    $billing->siret = '12345678901234';
    $billing->save();

    $shop = app(ShopSettings::class);
    $shop->admin_notification_email = 'admin@example.test';
    $shop->save();

    $this->order = Order::factory()->create([
        'status' => OrderStatus::PendingPayment,
        'payment_method' => PaymentMethod::Stripe,
        'email' => 'client@example.test',
        'total_ht' => 1000, 'total_vat' => 55, 'total_ttc' => 1055,
    ]);
    OrderItem::factory()->for($this->order)->create(['vat_rate' => 5.5, 'line_total_ht' => 1000, 'line_total_ttc' => 1055]);
});

function transitionOrder(Order $order, OrderStatus $to): Order
{
    return app(OrderStateMachine::class)->transition($order, $to, 'system');
}

test('commande payée par carte : le client reçoit une seule confirmation avec sa facture, l\'admin est prévenu', function () {
    transitionOrder($this->order, OrderStatus::Paid);

    Mail::assertQueuedCount(2);
    Mail::assertQueued(OrderConfirmedMail::class, fn (OrderConfirmedMail $mail) => $mail->hasTo('client@example.test')
        && $mail->invoice?->type === InvoiceType::Invoice);
    Mail::assertQueued(NewPaidOrderMail::class, fn ($mail) => $mail->hasTo('admin@example.test'));
});

test('l\'email de confirmation contient le lien de téléchargement de la facture', function () {
    transitionOrder($this->order, OrderStatus::Paid);
    $invoice = $this->order->invoices()->sole();

    (new OrderConfirmedMail($this->order->fresh(), $invoice))
        ->assertSeeInHtml($this->order->number)
        ->assertSeeInHtml('Télécharger ma facture')
        ->assertSeeInHtml('/factures/'.$invoice->id.'/telecharger');
});

test('virement validé : le client reçoit « paiement reçu » avec sa facture (et pas la confirmation carte)', function () {
    $this->order->update(['payment_method' => PaymentMethod::BankTransfer]);

    transitionOrder($this->order, OrderStatus::Paid);

    Mail::assertQueued(BankTransferPaidMail::class, fn ($mail) => $mail->invoice !== null);
    Mail::assertNotQueued(OrderConfirmedMail::class);
});

test('commande expédiée : le client reçoit le numéro et le lien de suivi', function () {
    $shipping = app(ShippingSettings::class);
    $shipping->tracking_url_template = 'https://suivi.example/?lt={tracking}';
    $shipping->save();
    $this->order->update(['status' => OrderStatus::Preparing]);

    app(ShipOrderAction::class)->execute($this->order, 'XY123', adminId: 1);

    Mail::assertQueued(OrderShippedMail::class, function (OrderShippedMail $mail) {
        $mail->assertSeeInHtml('XY123')->assertSeeInHtml('https://suivi.example/?lt=XY123');

        return $mail->hasTo('client@example.test');
    });
});

test('aucun email n\'est envoyé si le changement de statut est annulé', function () {
    $this->order->update(['status' => OrderStatus::Preparing]);

    try {
        DB::transaction(function () {
            transitionOrder($this->order, OrderStatus::Shipped);
            throw new RuntimeException('échec simulé');
        });
    } catch (RuntimeException) {
    }

    Mail::assertNotQueued(OrderShippedMail::class);
});

test('commande remboursée : le client reçoit la confirmation avec son avoir', function () {
    transitionOrder($this->order, OrderStatus::Paid);

    transitionOrder($this->order->fresh(), OrderStatus::Refunded);

    Mail::assertQueued(OrderRefundedMail::class, fn ($mail) => $mail->creditNote?->type === InvoiceType::CreditNote);
});

test('sans email admin paramétré, aucune notification admin n\'est envoyée', function () {
    $shop = app(ShopSettings::class);
    $shop->admin_notification_email = null;
    $shop->save();

    transitionOrder($this->order, OrderStatus::Paid);

    Mail::assertNotQueued(NewPaidOrderMail::class);
    Mail::assertQueued(OrderConfirmedMail::class);
});

test('une demande de suppression de compte prévient l\'admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('compte.deletion.request'))->assertRedirect();

    Mail::assertQueued(AccountDeletionRequestedMail::class, fn ($mail) => $mail->hasTo('admin@example.test'));
});

test('les emails de création de compte et de mot de passe oublié sont en français', function () {
    $user = User::factory()->make(['id' => 1, 'first_name' => 'Marie']);

    $verify = (new VerifyEmail)->toMail($user);
    $reset = (new ResetPassword('jeton'))->toMail($user);

    expect($verify->subject)->toBe('Bienvenue ! Confirmez votre adresse email')
        ->and((string) $verify->render())->toContain('Confirmer mon adresse email')
        ->and($reset->subject)->toBe('Réinitialisation de votre mot de passe')
        ->and((string) $reset->render())->toContain('Choisir un nouveau mot de passe');
});

test('« Renvoyer l\'email de confirmation » renvoie le bon email selon l\'état de la commande', function () {
    $resend = app(ResendOrderConfirmationAction::class);

    $transfer = Order::factory()->create(['payment_method' => PaymentMethod::BankTransfer, 'status' => OrderStatus::PendingPayment]);
    expect($resend->execute($transfer))->toBeTrue();
    Mail::assertQueued(BankTransferInstructionsMail::class);

    $paid = Order::factory()->create(['payment_method' => PaymentMethod::Stripe, 'status' => OrderStatus::Shipped]);
    expect($resend->execute($paid))->toBeTrue();
    Mail::assertQueued(OrderConfirmedMail::class);

    $cancelled = Order::factory()->create(['status' => OrderStatus::Cancelled]);
    expect(ResendOrderConfirmationAction::isAvailableFor($cancelled))->toBeFalse()
        ->and($resend->execute($cancelled))->toBeFalse();
});

test('la commande d\'exemples envoie les 14 emails sans rien laisser en base', function () {
    $ordersBefore = Order::query()->count();

    $this->artisan('emails:samples', ['to' => 'relecture@example.test'])->assertSuccessful();

    Mail::assertSentCount(12); // + 2 emails de compte envoyés directement en HTML
    expect(Order::query()->count())->toBe($ordersBefore);
});

test('chaque écouteur de commande n\'est enregistré qu\'une fois (pas de double email ni double facture)', function () {
    $raw = app('events')->getRawListeners();

    foreach ([OrderPaid::class => 2, OrderShipped::class => 1, OrderRefunded::class => 1] as $event => $expected) {
        $listeners = array_map(fn ($l) => is_array($l) ? implode('@', $l) : (is_string($l) ? $l : 'closure'), $raw[$event] ?? []);
        expect(count($listeners))->toBe($expected, "{$event} : ".implode(', ', $listeners))
            ->and(count(array_unique($listeners)))->toBe($expected);
    }
});

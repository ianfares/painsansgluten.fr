<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Mail\Admin\AccountDeletionRequestedMail;
use App\Mail\Admin\NewBankTransferOrderMail;
use App\Mail\Admin\NewPaidOrderMail;
use App\Mail\Admin\NewProAccountRequestMail;
use App\Mail\BankTransferCancelledMail;
use App\Mail\BankTransferInstructionsMail;
use App\Mail\BankTransferPaidMail;
use App\Mail\BankTransferReminderMail;
use App\Mail\ContactMessageMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderRefundedMail;
use App\Mail\OrderShippedMail;
use App\Mail\ProAccountRequestApprovedMail;
use App\Mail\ProAccountRequestReceivedMail;
use App\Mail\ProAccountRequestRejectedMail;
use App\Mail\StripePaymentAnomalyMail;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProAccountRequest;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie un exemplaire de chaque email de la boutique, rempli avec une
 * fausse commande, pour relecture (T19). Rien n'est conservé en base.
 *
 *   php artisan emails:samples webmaster@example.com
 */
class SendEmailSamplesCommand extends Command
{
    protected $signature = 'emails:samples {to : adresse qui reçoit les exemplaires}';

    protected $description = 'Envoie un exemplaire de chaque email de la boutique à une adresse (relecture des textes)';

    public function handle(): int
    {
        $to = (string) $this->argument('to');

        DB::beginTransaction();

        try {
            $samples = $this->samples();
            $total = count($samples);

            foreach ($samples as $i => [$label, $message]) {
                $number = $i + 1;
                $subject = "[EXEMPLE {$number}/{$total}] {$label}";

                if ($message instanceof Mailable) {
                    // Préfixe ajouté au dernier moment : le sujet défini par l'email est conservé.
                    $message->withSymfonyMessage(fn ($m) => $m->subject($subject.' — '.$m->getSubject()));
                    Mail::to($to)->send($message);
                } else {
                    Mail::html((string) $message->render(), fn ($m) => $m->to($to)->subject($subject.' — '.$message->subject));
                }

                $this->line("Envoyé : {$subject}");
            }
        } finally {
            DB::rollBack();
        }

        $this->info("{$total} exemplaires envoyés à {$to}.");

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: Mailable|MailMessage}>
     */
    private function samples(): array
    {
        $order = Order::factory()->create([
            'number' => 'C'.now()->year.'-00042',
            'first_name' => 'Marie',
            'last_name' => 'Exemple',
            'email' => 'marie.exemple@example.com',
            'relay_name' => 'Tabac de la Gare — Avranches',
            'subtotal_ttc' => 1990,
            'shipping_ttc' => 590,
            'total_ttc' => 2580,
            'planned_ship_date' => now()->addWeekdays(2)->toDateString(),
            'tracking_number' => 'XY123456789FR',
        ]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Pain de campagne sans gluten', 'unit_price_ttc' => 690, 'quantity' => 2, 'line_total_ttc' => 1380]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Croissants pur beurre (x2)', 'unit_price_ttc' => 610, 'quantity' => 1, 'line_total_ttc' => 610]);

        $invoice = Invoice::factory()->create(['order_id' => $order->id, 'type' => InvoiceType::Invoice]);
        $creditNote = Invoice::factory()->create(['order_id' => $order->id, 'type' => InvoiceType::CreditNote]);

        $card = $order->replicate()->fill(['payment_method' => PaymentMethod::Stripe, 'status' => OrderStatus::Paid]);
        $card->id = $order->id;
        $transfer = $order->replicate()->fill(['payment_method' => PaymentMethod::BankTransfer, 'status' => OrderStatus::PendingPayment]);
        $transfer->id = $order->id;
        foreach ([$card, $transfer] as $variant) {
            $variant->setRelation('items', $order->items);
        }

        $user = User::factory()->make(['id' => 1, 'first_name' => 'Marie', 'last_name' => 'Exemple', 'email' => 'marie.exemple@example.com']);

        $proRequest = ProAccountRequest::factory()->make([
            'id' => 1,
            'company_name' => 'Pizzeria Exemple',
            'contact_first_name' => 'Marie',
            'contact_last_name' => 'Exemple',
            'activity_type' => 'Pizzeria',
            'admin_comment' => 'Nous vous recontactons par téléphone cette semaine.',
        ]);

        return [
            ['Client — carte : commande confirmée', new OrderConfirmedMail($card, $invoice)],
            ['Client — virement : instructions de paiement', new BankTransferInstructionsMail($transfer)],
            ['Client — virement : relance', new BankTransferReminderMail($transfer)],
            ['Client — virement : paiement reçu', new BankTransferPaidMail($transfer, $invoice)],
            ['Client — virement : commande annulée', new BankTransferCancelledMail($transfer)],
            ['Client — commande expédiée', new OrderShippedMail($card)],
            ['Client — commande remboursée', new OrderRefundedMail($card, $creditNote)],
            ['Client — création de compte', (new VerifyEmail)->toMail($user)],
            ['Client — mot de passe oublié', (new ResetPassword('exemple-de-jeton'))->toMail($user)],
            ['Admin — nouvelle commande payée', new NewPaidOrderMail($card)],
            ['Admin — nouveau virement en attente', new NewBankTransferOrderMail($transfer)],
            ['Admin — anomalie de paiement Stripe', new StripePaymentAnomalyMail($card, 'Un paiement de 25,80 € a été encaissé alors que la commande était déjà « Payée » (paiement en double ou commande annulée). Remboursez-le depuis le tableau de bord Stripe.')],
            ['Admin — demande de suppression de compte', new AccountDeletionRequestedMail($user)],
            ['Client pro — demande reçue', new ProAccountRequestReceivedMail($proRequest)],
            ['Client pro — demande approuvée', new ProAccountRequestApprovedMail($proRequest)],
            ['Client pro — demande refusée', new ProAccountRequestRejectedMail($proRequest)],
            ['Admin — nouvelle demande de compte pro', new NewProAccountRequestMail($proRequest)],
            ['Admin — message du formulaire de contact', new ContactMessageMail(['name' => 'Marie Exemple', 'email' => 'marie.exemple@example.com', 'phone' => '06 12 34 56 78', 'message' => "Bonjour,\nlivrez-vous à Granville ? Je voudrais commander pour samedi.\nMerci !"])],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Une session Stripe déjà ouverte pour la commande a été payée : on n'en
 * ouvre pas une nouvelle (le webhook va confirmer le paiement).
 */
class PaymentAlreadyCompleted extends RuntimeException {}

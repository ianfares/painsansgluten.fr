<?php

declare(strict_types=1);

namespace App\Services\Chronopost;

use App\Exceptions\Shipping\ShippingLabelFailed;
use App\Models\Order;
use App\Settings\ShippingSettings;
use App\Settings\ShopSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Étiquettes Chronopost Relais (T28, PLAN.md §8.2) :
 * - création : `shippingMultiParcelV4`, un colis par commande, PDF A4 ou 10×15 thermique (réglage BO) ;
 * - annulation : `cancelListSkybill`.
 *
 * Règles imposées par Chronopost pour un envoi en relais (email du 26/10/2026) :
 * adresse du relais en destinataire, nom du client en `recipientName2`,
 * portable + email du client, code du relais en `idRelais`.
 * Expéditeur = coordonnées de la boutique (réglages BO).
 */
class ShippingLabelClient
{
    private const SUCCESS_CODE = 0;

    /** Longueurs maximales Chronopost (doc Web Services §2.3.2). */
    private const MAX_ADDRESS = 38;

    private const MAX_CITY = 50;

    private const MAX_NAME = 100;

    private const MAX_EMAIL = 80;

    public function __construct(
        private readonly SoapClientFactory $soapClientFactory,
        private readonly ShippingSettings $shippingSettings,
        private readonly ShopSettings $shopSettings,
    ) {}

    /**
     * @throws ShippingLabelFailed configuration incomplète, service injoignable ou refus Chronopost
     */
    public function create(Order $order, int $weightGrams): CreatedLabel
    {
        $credentials = $this->credentials();
        $productCode = (string) $this->shippingSettings->chronopost_product_code;
        if ($productCode === '') {
            throw new ShippingLabelFailed('Code produit Chronopost manquant (Paramètres > Expédition).');
        }

        $now = CarbonImmutable::now('Europe/Paris');
        $result = $this->call('shipping_wsdl', 'shippingMultiParcelV4', [
            'headerValue' => ['accountNumber' => $credentials['account'], 'idEmit' => 'CHRFR', 'identWebPro' => '', 'subAccount' => ''],
            'shipperValue' => $this->shipper(),
            'customerValue' => $this->customer(),
            'recipientValue' => $this->recipient($order),
            'refValue' => [
                'customerSkybillNumber' => '',
                'recipientRef' => self::text($order->number, 35),
                'shipperRef' => self::text($order->number, 35),
                'idRelais' => (string) $order->relay_id,
            ],
            'skybillValue' => [
                'bulkNumber' => 1,
                'codCurrency' => 'EUR',
                'codValue' => 0,
                'evtCode' => 'DC',
                'insuredCurrency' => 'EUR',
                'insuredValue' => 0, // garantie standard, pas d'assurance ad valorem (décision de Ian)
                'objectType' => 'MAR',
                'portCurrency' => 'EUR',
                'portValue' => 0,
                'productCode' => $productCode,
                'service' => (string) config('services.chronopost.label_service'),
                // Date/heure de génération de l'envoi (valeur par défaut de la doc, élément exigé par le WSDL).
                'shipDate' => $now->format('Y-m-d\\TH:i:s'),
                'shipHour' => (int) $now->format('G'),
                'skybillRank' => 1,
                'weight' => round($weightGrams / 1000, 2),
                'weightUnit' => 'KGM',
                // Carton variable selon la commande : dimensions inconnues (0 autorisé par la doc).
                'height' => 0,
                'length' => 0,
                'width' => 0,
            ],
            'skybillParamsValue' => ['duplicata' => 'N', 'mode' => $this->shippingSettings->chronopost_label_mode, 'withReservation' => 0],
            'password' => $credentials['password'],
            'modeRetour' => '2',
            'numberOfParcel' => 1,
            'version' => '2.0',
            'multiParcel' => 'N',
        ], $order);

        $parcel = $result->resultMultiParcelValue ?? null;
        $parcel = is_array($parcel) ? ($parcel[0] ?? null) : $parcel;
        $skybill = is_object($parcel) ? trim((string) ($parcel->skybillNumber ?? '')) : '';
        $pdf = is_object($parcel) ? (string) ($parcel->pdfEtiquette ?? '') : '';

        // pdfEtiquette est un base64Binary : SoapClient le décode déjà en binaire.
        if ($skybill === '' || ! str_starts_with($pdf, '%PDF')) {
            Log::error('Chronopost : réponse d\'étiquette incomplète.', ['order_id' => $order->id]);

            throw new ShippingLabelFailed('Réponse Chronopost incomplète : étiquette non créée.');
        }

        return new CreatedLabel($skybill, isset($result->reservationNumber) ? (string) $result->reservationNumber : null, $pdf);
    }

    /**
     * @throws ShippingLabelFailed
     */
    public function cancel(string $skybillNumber, Order $order): void
    {
        $credentials = $this->credentials();

        $this->call('tracking_wsdl', 'cancelListSkybill', [
            'accountNumber' => $credentials['account'],
            'password' => $credentials['password'],
            'language' => 'fr_FR',
            'skybillNumber' => $skybillNumber,
        ], $order);
    }

    /** @return array{account: string, password: string} */
    private function credentials(): array
    {
        $account = (string) config('services.chronopost.account_number');
        $password = (string) config('services.chronopost.password');
        if ($account === '' || $password === '') {
            throw new ShippingLabelFailed('Identifiants Chronopost manquants dans le fichier .env.');
        }

        return ['account' => $account, 'password' => $password];
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws ShippingLabelFailed
     */
    private function call(string $wsdlKey, string $method, array $parameters, Order $order): object
    {
        try {
            $client = $this->soapClientFactory->make(
                (string) config('services.chronopost.'.$wsdlKey),
                (int) config('services.chronopost.label_timeout_seconds'),
            );
            $response = $client->__soapCall($method, [$parameters]);
        } catch (Throwable $e) {
            Log::warning("Chronopost : {$method} en échec.", ['order_id' => $order->id, 'error' => $e->getMessage()]);

            throw new ShippingLabelFailed('Chronopost est injoignable pour le moment : réessayez dans quelques minutes.', previous: $e);
        }

        $result = is_object($response) && isset($response->return) && is_object($response->return) ? $response->return : null;
        $errorCode = $result !== null && isset($result->errorCode) ? (int) $result->errorCode : null;

        if ($result === null || $errorCode !== self::SUCCESS_CODE) {
            $message = trim((string) ($result->errorMessage ?? ''));
            Log::warning("Chronopost : {$method} refusé.", ['order_id' => $order->id, 'error_code' => $errorCode, 'error_message' => $message]);

            throw new ShippingLabelFailed('Chronopost a refusé la demande'.($message !== '' ? ' : '.$message : '.').' (code '.($errorCode ?? '?').')');
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function shipper(): array
    {
        $shop = $this->shopSettings;
        $this->assertShopConfigured();

        return [
            'shipperAdress1' => self::text((string) $shop->address_line1, self::MAX_ADDRESS),
            'shipperAdress2' => '',
            'shipperCity' => self::text((string) $shop->city, self::MAX_CITY),
            'shipperCivility' => 'M',
            'shipperContactName' => self::text((string) $shop->shop_name, self::MAX_NAME),
            'shipperCountry' => 'FR',
            'shipperCountryName' => 'FRANCE',
            'shipperEmail' => Str::limit((string) $shop->contact_email, self::MAX_EMAIL, ''),
            'shipperMobilePhone' => '',
            'shipperName' => self::text((string) $shop->shop_name, self::MAX_NAME),
            'shipperName2' => '',
            'shipperPhone' => self::phone((string) $shop->contact_phone),
            'shipperPreAlert' => 0,
            'shipperZipCode' => (string) $shop->postal_code,
            'shipperType' => '1',
        ];
    }

    /** Le « client » Chronopost (titulaire du contrat) est la boutique elle-même. */
    private function customer(): array
    {
        $shipper = $this->shipper();

        return [
            'customerAdress1' => $shipper['shipperAdress1'],
            'customerAdress2' => '',
            'customerCity' => $shipper['shipperCity'],
            'customerCivility' => 'M',
            'customerContactName' => $shipper['shipperContactName'],
            'customerCountry' => 'FR',
            'customerCountryName' => 'FRANCE',
            'customerEmail' => $shipper['shipperEmail'],
            'customerMobilePhone' => '',
            'customerName' => $shipper['shipperName'],
            'customerName2' => '',
            'customerPhone' => $shipper['shipperPhone'],
            'customerPreAlert' => 0,
            'customerZipCode' => $shipper['shipperZipCode'],
            'printAsSender' => 'N',
        ];
    }

    /** @return array<string, mixed> */
    private function recipient(Order $order): array
    {
        $relay = (array) $order->relay_snapshot;
        $customerName = $order->hasDistinctShippingAddress()
            ? $order->shipping_first_name.' '.$order->shipping_last_name
            : $order->first_name.' '.$order->last_name;
        $mobile = self::phone((string) $order->phone);

        return [
            'recipientName' => self::text((string) ($relay['name'] ?? $order->relay_name), self::MAX_NAME),
            'recipientName2' => self::text($customerName, self::MAX_NAME),
            'recipientAdress1' => self::text((string) ($relay['address_line1'] ?? ''), self::MAX_ADDRESS),
            'recipientAdress2' => '',
            'recipientZipCode' => (string) ($relay['postal_code'] ?? ''),
            'recipientCity' => self::text((string) ($relay['city'] ?? ''), self::MAX_CITY),
            'recipientCountry' => 'FR',
            'recipientContactName' => self::text($customerName, self::MAX_NAME),
            'recipientEmail' => Str::limit((string) $order->email, self::MAX_EMAIL, ''),
            'recipientPhone' => $mobile,
            'recipientMobilePhone' => $mobile,
            'recipientPreAlert' => 0,
            'recipientType' => '2',
        ];
    }

    private function assertShopConfigured(): void
    {
        $shop = $this->shopSettings;
        foreach (['shop_name' => 'nom de la boutique', 'address_line1' => 'adresse', 'postal_code' => 'code postal', 'city' => 'ville', 'contact_phone' => 'téléphone', 'contact_email' => 'email de contact'] as $field => $label) {
            if (blank($shop->{$field})) {
                throw new ShippingLabelFailed("Coordonnées de l'expéditeur incomplètes : {$label} à renseigner dans Paramètres > Boutique.");
            }
        }
    }

    /** Téléphone au format Chronopost (chiffres seuls, +33 → 0). */
    private static function phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($phone, '+33') ? '0'.substr($digits, 2) : $digits;
    }

    /** Texte sans accents, tronqué à la longueur maximale Chronopost. */
    private static function text(string $value, int $max): string
    {
        return mb_substr(trim(Str::ascii($value)), 0, $max);
    }
}

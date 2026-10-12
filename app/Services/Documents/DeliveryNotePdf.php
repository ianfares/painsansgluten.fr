<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Order;
use App\Settings\ShopSettings;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * Bon de livraison PDF (T27-L8), généré à la volée (jamais stocké : données
 * personnelles) et réservé aux admins. Aucun prix. Un QR code (correction
 * d'erreur M) reprend les informations du bon en texte.
 */
class DeliveryNotePdf
{
    /** Au-delà, le QR devient trop dense pour être lu de façon fiable à 3,6 cm. */
    private const QR_MAX_BYTES = 600;

    /** Le bon n'est disponible que pour les commandes payées (et non remboursées). */
    public static function isAvailableFor(Order $order): bool
    {
        return $order->status->isPaidState();
    }

    /**
     * @param  iterable<Order>  $orders  les commandes non payées sont ignorées
     */
    public function render(iterable $orders): string
    {
        return Pdf::loadView('pdf.delivery-note', $this->viewData($orders))->setPaper('a4')->output();
    }

    /**
     * @param  iterable<Order>  $orders
     * @return array<string, mixed>
     */
    public function viewData(iterable $orders): array
    {
        $notes = Collection::make($orders)
            ->filter(fn (Order $order): bool => self::isAvailableFor($order))
            ->map(function (Order $order): array {
                $order->loadMissing('items.product');

                return [
                    'order' => $order,
                    'address' => $this->deliveryAddress($order),
                    'items' => $order->items,
                    'totalQuantity' => (int) $order->items->sum('quantity'),
                    'qr' => $this->qrSvgDataUri($this->qrText($order)),
                ];
            })
            ->values();

        $shop = app(ShopSettings::class);

        return [
            'notes' => $notes,
            'shopName' => $shop->shop_name,
            'shopAddress' => trim(($shop->address_line1 ?? '').' '.($shop->postal_code ?? '').' '.($shop->city ?? '')),
            'shopPhone' => $shop->contact_phone,
        ];
    }

    /** Adresse de livraison en une ligne (point relais, commerçant ou labo). */
    public function deliveryAddress(Order $order): string
    {
        $snapshot = $order->relay_snapshot ?? [];
        $parts = array_filter([
            $order->relay_name ?: ($snapshot['name'] ?? null),
            $snapshot['address_line1'] ?? null,
            trim(($snapshot['postal_code'] ?? '').' '.($snapshot['city'] ?? '')),
        ], fn ($part): bool => is_string($part) && trim($part) !== '');

        return implode(', ', $parts);
    }

    /**
     * Texte encodé dans le QR, au format lu par le fichier Excel « scan BL » du
     * labo (le même que l'ancien modèle Shopify) : UNE seule ligne, sans retour
     * à la ligne (la scanette les tape comme « Entrée » et éclate le code sur
     * plusieurs cellules) :
     *   BL=BL-{n°}|CMD={n°}|DATE=AAAA-MM-JJ|CLIENT={nom}|ART1={réf}|QTE1={qté}|ART2=…
     * Si la commande a trop de lignes, les derniers articles sont omis (paires
     * ART/QTE complètes uniquement) : n° de commande, date et client restent.
     */
    public function qrText(Order $order): string
    {
        $order->loadMissing('items');

        $text = implode('|', [
            'BL=BL-'.$this->qrValue($order->number),
            'CMD='.$this->qrValue($order->number),
            'DATE='.$order->created_at->timezone('Europe/Paris')->format('Y-m-d'),
            'CLIENT='.$this->qrValue($order->first_name.' '.$order->last_name),
        ]);

        foreach ($order->items->values() as $index => $item) {
            $n = $index + 1;
            $pair = '|ART'.$n.'='.$this->qrValue($item->product_reference).'|QTE'.$n.'='.$item->quantity;
            if (strlen($text.$pair) > self::QR_MAX_BYTES) {
                break;
            }
            $text .= $pair;
        }

        return $text;
    }

    /** Valeur sans séparateur (« | », « = ») ni retour à la ligne, pour ne pas casser la lecture d'Excel. */
    private function qrValue(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace(['|', '='], ' ', $value)));
    }

    private function qrSvgDataUri(string $text): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(300, 1), new SvgImageBackEnd));
        $svg = $writer->writeString($text, 'UTF-8', ErrorCorrectionLevel::M());

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}

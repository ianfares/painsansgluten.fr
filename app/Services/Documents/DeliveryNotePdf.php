<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Order;
use App\Models\OrderItem;
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
     * Texte encodé dans le QR. Le n° de commande, le client et l'adresse sont
     * toujours présents ; la liste des produits est tronquée si besoin.
     */
    public function qrText(Order $order): string
    {
        $order->loadMissing('items');

        $head = implode("\n", [
            'Bon de livraison '.$order->number,
            'Date : '.$order->created_at->timezone('Europe/Paris')->format('d/m/Y'),
            'Client : '.$order->first_name.' '.$order->last_name.' - '.$order->phone,
            'Livraison : '.$order->delivery_method->label().' - '.$this->deliveryAddress($order),
        ]);

        $lines = $order->items->map(fn (OrderItem $item): string => $item->quantity.' x '.$item->product_name)->all();

        $full = $head."\n".implode("\n", $lines);
        if (strlen($full) <= self::QR_MAX_BYTES) {
            return $full;
        }

        $marker = "\n... (voir BL)";
        $text = $head;
        foreach ($lines as $line) {
            if (strlen($text."\n".$line.$marker) > self::QR_MAX_BYTES) {
                break;
            }
            $text .= "\n".$line;
        }

        return $text.$marker;
    }

    private function qrSvgDataUri(string $text): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(300, 1), new SvgImageBackEnd));
        $svg = $writer->writeString($text, 'UTF-8', ErrorCorrectionLevel::M());

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}

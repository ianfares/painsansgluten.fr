@props(['totals', 'shippingLabel' => 'Frais de port', 'pickup' => false])
{{-- Récapitulatif pour un compte professionnel (15/10/2026) : HT d'abord, TVA, puis TTC payé. --}}
@php($eur = fn (?int $cents): string => number_format(((int) $cents) / 100, 2, ',', ' ').' €')
<div class="flex justify-between"><span>Sous-total HT @if ($totals['discount_percent'] > 0)<span class="text-sage-dark">(remise -{{ $totals['discount_percent'] }} % déduite)</span>@endif</span><span>{{ $eur($totals['subtotal_ht']) }}</span></div>
<div class="flex justify-between gap-4"><span>{{ $shippingLabel }} HT</span><span class="whitespace-nowrap">{{ $pickup ? 'Gratuit' : ($totals['shipping_ht'] === 0 ? 'Offerts' : $eur($totals['shipping_ht'])) }}</span></div>
<div class="flex justify-between font-semibold"><span>Total HT</span><span>{{ $eur($totals['total_ht']) }}</span></div>
<div class="flex justify-between text-ink-muted"><span>TVA</span><span>{{ $eur($totals['total_vat']) }}</span></div>
<div class="flex justify-between text-base font-semibold"><span>Total TTC</span><span>{{ $eur($totals['total_ttc']) }}</span></div>

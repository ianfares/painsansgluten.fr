<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\PushesAnalyticsEvents;
use App\Models\Product;
use App\Services\Analytics\Ecommerce;
use App\Services\Cart\CartService;
use Livewire\Component;

class AddToCartButton extends Component
{
    use PushesAnalyticsEvents;

    public Product $product;

    public bool $withQuantity = false;

    public int $quantity = 1;

    public bool $added = false;

    public function add(): void
    {
        abort_unless($this->product->isOrderable(), 403);

        app(CartService::class)->add($this->product, max(1, $this->quantity));
        $this->pushAnalytics('add_to_cart', app(Ecommerce::class)->single($this->product, auth()->user(), max(1, $this->quantity)));

        $this->added = true;
        $this->dispatch('cart-updated')->to(CartWidget::class);
        $this->dispatch('open-drawer-cart');
    }

    public function incrementQuantity(): void
    {
        $this->quantity++;
    }

    public function decrementQuantity(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function render()
    {
        return view('livewire.add-to-cart-button');
    }
}

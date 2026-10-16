<?php

namespace Bakery\Shop;

use Bakery\Catalog\Product;
use Bakery\{Format, Language};
use Waypoint\Attributes\Inject;
use Waypoint\Live\Component;

/** The lines of the cart page. The buttons are bound to the product on the server: the browser only clicks. */
#[Component]
final class CartEditor
{
    #[Inject]
    private Cart $cart;

    #[Inject]
    private Language $i18n;

    public function more(Product $product): void
    {
        $this->cart->add($product);
    }

    public function less(Product $product): void
    {
        $this->cart->set($product, $this->cart->quantity($product->id) - 1);
    }

    public function remove(Product $product): void
    {
        $this->cart->set($product, 0);
    }

    private function price(int $cents): string
    {
        return Format::price($cents, $this->i18n->locale());
    }
}

<?php

namespace Bakery\Shop;

use Bakery\Catalog\Product;

/** A product in the cart and how many of it. */
final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
    ) {
    }

    public function totalCents(): int
    {
        return $this->product->priceCents * $this->quantity;
    }
}

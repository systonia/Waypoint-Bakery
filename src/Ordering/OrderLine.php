<?php

namespace Bakery\Ordering;

/** One product of an order, with the name and price it had when the order was placed. */
final readonly class OrderLine
{
    public function __construct(
        public int $product,
        public string $name,
        public int $priceCents,
        public int $quantity,
    ) {
    }

    public function totalCents(): int
    {
        return $this->priceCents * $this->quantity;
    }
}

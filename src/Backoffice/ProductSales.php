<?php

namespace Bakery\Backoffice;

use Waypoint\Support\Row;

/** How many of a product were sold, under its name in the page's language. */
final readonly class ProductSales
{
    public function __construct(
        public string $product,
        public int $quantity,
    ) {
    }

    public static function of(Row $row): self
    {
        return new self($row->string('product'), $row->int('quantity'));
    }
}

<?php

namespace Bakery\Shop;

use Bakery\Catalog\Product;

/** What the product page shows: the product and its next pickup days with the weather. */
final readonly class ProductPage
{
    /**
     * @param list<string> $days Y-m-d
     * @param array<string, array{en: string, de: string}> $closed the holidays of the coming week, Y-m-d => name
     * @param array<string, array{code: int, max: float, rain: int}>|null $weather Y-m-d => forecast, null when unavailable
     */
    public function __construct(
        public Product $product,
        public array $days,
        public array $closed,
        public bool $holidaysKnown,
        public ?array $weather,
    ) {
    }
}

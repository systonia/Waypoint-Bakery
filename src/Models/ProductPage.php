<?php

namespace Bakery\Models;


/** What the product page shows: the product and its next pickup days with the weather. */
final readonly class ProductPage
{
    /**
     * @param list<string> $days Y-m-d
     * @param array<string, Holiday> $closed the holidays of the coming week, Y-m-d => holiday
     * @param array<string, WeatherDay>|null $weather Y-m-d => forecast, null when unavailable
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

<?php

namespace Bakery\Api;

use Bakery\Catalog\Product;

/** A product as the API shows it: its name in every language, the price in cents and what is left. */
final readonly class ProductSummary
{
    public function __construct(
        public string $slug,
        /** @var array<string, string> locale => name */
        public array $name,
        public string $category,
        public int $priceCents,
        public int $stock,
    ) {
    }

    public static function of(Product $product): self
    {
        return new self($product->slug, $product->name, $product->category, $product->priceCents, $product->stock);
    }
}

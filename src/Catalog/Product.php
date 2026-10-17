<?php

namespace Bakery\Catalog;

/** One thing the bakery sells, with its name and description in every language of the shop. */
final readonly class Product
{
    /**
     * @param array<string, string> $name locale => name
     * @param array<string, string> $description locale => text
     */
    public function __construct(
        public int $id,
        public string $slug,
        public array $name,
        public array $description,
        public string $category,
        public int $priceCents,
        public int $stock,
        public string $picture,
    ) {
    }

    public function name(string $locale): string
    {
        return $this->name[$locale] ?? $this->name['en'];
    }

    public function description(string $locale): string
    {
        return $this->description[$locale] ?? $this->description['en'];
    }
}

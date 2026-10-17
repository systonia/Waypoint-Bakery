<?php

namespace Bakery\Catalog;

use Waypoint\Attributes\{Ignore, ListOf};

/** One thing the bakery sells, with its name and description in every language of the shop. */
final readonly class Product
{
    public function __construct(
        #[Ignore]
        public int $id,
        public string $slug,
        #[ListOf(
            type: 'string',
            map: true
        )]
        public array $name,
        #[ListOf(
            type: 'string',
            map: true
        )]
        public array $description,
        public string $category,
        public int $priceCents,
        public int $stock,
        #[Ignore]
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

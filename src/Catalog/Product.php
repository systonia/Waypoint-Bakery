<?php

namespace Bakery\Catalog;

use Waypoint\Attributes\{Ignore, ListOf};
use Waypoint\Support\Row;

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

    /** A row with Products::COLUMNS. */
    public static function of(Row $row): self
    {
        return new self(
            id: $row->int('id'),
            slug: $row->string('slug'),
            name: $row->map('name', 'string'),
            description: $row->map('description', 'string'),
            category: $row->string('category'),
            priceCents: $row->int('priceCents'),
            stock: $row->int('stock'),
            picture: $row->string('picture'),
        );
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

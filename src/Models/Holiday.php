<?php

namespace Bakery\Models;

/** A public holiday's name in English and in the country's language. */
final readonly class Holiday
{
    public function __construct(
        public string $en,
        public string $de,
    ) {
    }

    public function name(string $locale): string
    {
        return $locale === 'de' ? $this->de : $this->en;
    }
}

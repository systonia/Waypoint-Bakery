<?php

namespace Bakery\Models;

/** A weekday the bakery is open, ISO numbered (1 = Monday), with its hours as HH:MM. */
final readonly class OpeningDay
{
    public function __construct(
        public int $weekday,
        public string $opens,
        public string $closes,
    ) {
    }
}

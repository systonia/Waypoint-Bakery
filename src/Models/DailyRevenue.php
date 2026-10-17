<?php

namespace Bakery\Models;

use Waypoint\Support\Row;

/** One day's revenue in euros, the day formatted for the chart. */
final readonly class DailyRevenue
{
    public function __construct(
        public string $day,
        public float $revenue,
    ) {
    }

    public static function of(Row $row): self
    {
        return new self($row->string('day'), $row->float('revenue'));
    }
}

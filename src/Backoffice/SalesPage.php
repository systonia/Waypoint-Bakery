<?php

namespace Bakery\Backoffice;

use Waypoint\Charts\Chart;
use Waypoint\Export\Sheet;

/** The owner's sales page: two charts and the orders of the last seven days. */
final readonly class SalesPage
{
    public function __construct(
        public Chart $revenue,
        public Chart $products,
        public Sheet $latest,
    ) {
    }
}

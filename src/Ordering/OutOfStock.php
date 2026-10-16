<?php

namespace Bakery\Ordering;

use RuntimeException;

/** An order wanted more than is left; nothing was reserved. */
final class OutOfStock extends RuntimeException
{
    public function __construct(public readonly string $product)
    {
        parent::__construct("Not enough $product left");
    }
}

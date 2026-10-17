<?php

namespace Bakery\Requests;

use Bakery\Models\OrderStatus;
use Waypoint\Attributes\Property;
use Waypoint\FromArray;

final class StatusChange
{
    use FromArray;

    #[Property(
        description: 'The next step of the order: received → baking → ready → picked_up',
        example: 'baking'
    )]
    public ?OrderStatus $status = null;
}

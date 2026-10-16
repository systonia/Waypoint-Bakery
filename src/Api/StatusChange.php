<?php

namespace Bakery\Api;

use Waypoint\Attributes\{NotBlank, OneOf, Property};
use Waypoint\FromArray;

final class StatusChange
{
    use FromArray;

    #[NotBlank]
    #[OneOf(['baking', 'ready', 'picked_up'])]
    #[Property(
        description: 'The next step of the order: received → baking → ready → picked_up',
        example: 'baking'
    )]
    public string $status = '';
}

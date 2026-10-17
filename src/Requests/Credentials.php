<?php

namespace Bakery\Requests;

use Waypoint\Attributes\{Email, NotBlank, Property};
use Waypoint\FromArray;

final class Credentials
{
    use FromArray;

    #[NotBlank]
    #[Email]
    #[Property(
        example: 'ben@sonnenkorn.test'
    )]
    public string $email = '';

    #[NotBlank]
    #[Property(
        example: 'pretzel'
    )]
    public string $password = '';
}

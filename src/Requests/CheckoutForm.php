<?php

namespace Bakery\Requests;

use Waypoint\Attributes\{Email, Length, NotBlank, Regex};
use Waypoint\FromArray;

/** What the checkout asks for. Waypoint's validation attributes, checked inside a live action. */
final class CheckoutForm
{
    use FromArray;

    #[NotBlank]
    #[Length(
        max: 80
    )]
    public string $name = '';

    #[NotBlank]
    #[Email]
    public string $email = '';

    #[NotBlank]
    #[Regex('/^\d{4}-\d{2}-\d{2}$/')]
    public string $pickupDate = '';
}

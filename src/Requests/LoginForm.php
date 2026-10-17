<?php

namespace Bakery\Requests;

use Waypoint\Attributes\{Email, NotBlank};
use Waypoint\FromArray;

final class LoginForm
{
    use FromArray;

    #[NotBlank]
    #[Email]
    public string $email = '';

    #[NotBlank]
    public string $password = '';
}

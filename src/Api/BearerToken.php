<?php

namespace Bakery\Api;

/** What POST /v1/tokens answers: the token to send as `Authorization: Bearer …`. */
final readonly class BearerToken
{
    public function __construct(public string $token)
    {
    }
}

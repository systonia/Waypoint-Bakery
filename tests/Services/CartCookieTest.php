<?php

namespace Bakery\Tests\Services;

use Bakery\Tests\Support\AppTestCase;

final class CartCookieTest extends AppTestCase
{
    public function testEveryBrowserGetsACartCookieOnce(): void
    {
        $cookie = $this->get('/')->cookie('cart') ?? '';
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $cookie);
        $this->assertNull($this->request('GET', '/')->withCookie('cart', $cookie)->send()->cookie('cart'));
    }
}

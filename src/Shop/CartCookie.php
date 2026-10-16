<?php

namespace Bakery\Shop;

use Waypoint\Http\{MiddlewareBase, Request, Response};

/** Gives every browser a cart id in a cookie, so its cart survives going from page to page. */
final class CartCookie extends MiddlewareBase
{
    public const NAME = 'cart';

    public static function valid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) === 1;
    }

    protected function before(Request $req, Response $res): bool
    {
        if ($req->method === 'GET' && !self::valid((string) ($req->cookies[self::NAME] ?? ''))) {
            $bytes = random_bytes(16);
            $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
            $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
            $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
            // This request's components see it already, the browser sends it from the next request on
            $req->cookies[self::NAME] = $id;
            $res->withCookie(self::NAME, $id, maxAge: 30 * 86400, secure: $req->https);
        }
        return true;
    }
}

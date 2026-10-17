<?php

namespace Bakery\Services;

use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\Support\Uuid;

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
        if ($req->method === 'GET' && !self::valid($req->cookies[self::NAME] ?? '')) {
            // This request's components see it already, the browser sends it from the next request on
            $req->cookies[self::NAME] = Uuid::v4();
            $res->withCookie(self::NAME, $req->cookies[self::NAME], maxAge: 30 * 86400);
        }
        return true;
    }
}

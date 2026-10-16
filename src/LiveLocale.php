<?php

namespace Bakery;

use Waypoint\Container;
use Waypoint\Http\{MiddlewareBase, Request, Response};
use Waypoint\I18n\Translator;

/**
 * Hands the request's locale on to the live components' WebSocket. LocaleMiddleware resets the Translator to the
 * default once a request is done, and the WebSocket handler only runs after that, so it would start out in the
 * default language. This middleware is registered before the plugins, so its after() runs last and puts the
 * locale back for an upgraded request (Waypoint keeps that request's context for the WebSocket).
 */
final class LiveLocale extends MiddlewareBase
{
    public function __construct(private readonly Container $container)
    {
    }

    protected function after(Request $req, Response $res): void
    {
        $locale = $res->getHeaders()['Content-Language'] ?? null;
        if ($res->getUpgrade() !== null && is_string($locale)) {
            // Looked up here, not injected: the I18n plugin creates its Translator only when the app boots
            $this->container->get(Translator::class)->setLocale($locale);
        }
    }
}

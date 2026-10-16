<?php
// The bakery as a Waypoint app: its plugins, options, services and controllers. index.php serves it, the tests
// and the scripts in bin/ load it too. Waypoint::create() always returns the same app.

require __DIR__ . '/vendor/autoload.php';

use Bakery\Api\ApiController;
use Bakery\Backoffice\{BackofficeController, OrderBoard, SessionController, StockEditor};
use Bakery\Database\DatabaseTasks;
use Bakery\LiveLocale;
use Bakery\Shop\{AddToCart, CartBadge, CartCookie, CartEditor, Checkout, OrderTracker, ShopController};
use Tenma\Db\Postgres;
use Waypoint\Charts\{ChartsOptions, ChartsPlugin};
use Waypoint\Environment;
use Waypoint\Export\{ExportOptions, ExportPlugin};
use Waypoint\Flash\{FlashOptions, FlashPlugin};
use Waypoint\Http\SecurityHeadersMiddleware;
use Waypoint\I18n\{I18nOptions, I18nPlugin};
use Waypoint\Live\LivePlugin;
use Waypoint\Options\{CsrfOptions, EnvironmentOptions, FileSystemOptions, JWTOptions, OpenAPIOptions, RendererOptions};
use Waypoint\Waypoint;

$app = Waypoint::create();

// .env, config.json and config.<APP_ENV>.json next to this file
$app->configure(fn (EnvironmentOptions $env) => $env->load(__DIR__));
$env = $app->get(Environment::class) ?? throw new LogicException('No environment');
date_default_timezone_set((string) $env->get('BAKERY_TIMEZONE', 'Europe/Berlin'));

$app->plugin(new LivePlugin([
    CartBadge::class, AddToCart::class, CartEditor::class, Checkout::class, OrderTracker::class,
    OrderBoard::class, StockEditor::class,
]));
$app->plugin(new I18nPlugin());
$app->plugin(new FlashPlugin());
$app->plugin(new ChartsPlugin());
$app->plugin(new ExportPlugin());

$app->configure(function (FileSystemOptions $fs, RendererOptions $views, JWTOptions $jwt, CsrfOptions $csrf, OpenAPIOptions $api, Environment $env) {
    $fs->publicDirectory = __DIR__ . '/public';
    $views->directory = __DIR__ . '/views';
    $views->layout = '_Layout.php';

    // The back office signs in with a JWT in an HttpOnly cookie; forms and fetch() carry CSRF tokens
    $jwt->secret = (string) $env->get('JWT_SECRET');
    $jwt->cookieName = 'session';
    $jwt->ttl = 12 * 3600;
    $jwt->loginRedirectUrl = '/backoffice/login';
    $csrf->secret = (string) $env->get('CSRF_SECRET');
    $csrf->cookieSecure = !$env->isDev();

    $api->enabled = (bool) $env->get('OPENAPI', false);
    $api->title = 'Sonnenkorn bakery API';
    $api->version = '1.0.0';
    $api->description = 'The range, and the open orders for the people in the bakery.';
    $api->securitySchemes = ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT']];
});

$app->configure(function (I18nOptions $i18n, FlashOptions $flash, ChartsOptions $charts, ExportOptions $export, Environment $env) {
    $i18n->directory = __DIR__ . '/lang';
    $i18n->default = 'en';
    $i18n->supported = ['en', 'de'];
    $i18n->scanPaths = [__DIR__ . '/views', __DIR__ . '/src'];
    $flash->cookieSecure = !$env->isDev();
    $charts->format = fn (float $value, int $decimals): string => number_format($value, $decimals, ',', '.');
    $export->csv->encoding = 'UTF-8';
});

// One pool of Postgres connections per worker, shared by every service that injects Postgres
$app->getContainer()->set(Postgres::connect((string) $env->get('DATABASE_URL', 'postgres://localhost/bakery')));

$app->use(new SecurityHeadersMiddleware());
$app->use(new LiveLocale($app->getContainer()));
$app->use(new CartCookie());
$app->useJwt();

$app->attach([
    ShopController::class, SessionController::class, BackofficeController::class, ApiController::class,
    DatabaseTasks::class,
]);

return $app;

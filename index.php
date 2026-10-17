<?php
// The bakery under Tenma: tenma --watch index.php (development) or tenma --workers auto index.php. With arguments
// it runs a CLI task instead: tenma index.php db:reset.
// This script runs once per worker. What it sets up here stays in memory and serves every request.

use Bakery\Repositories\{Orders, SalesReport};
use Bakery\Services\LastKnown;
use Waypoint\Environment;
use Waypoint\Live\Broadcast;

/** @var Waypoint\App $app */
$app = require __DIR__ . '/app.php';
$env = $app->get(Environment::class);

// Background jobs in the same process: each runs once at start, then every <setting> seconds; 0 turns it off.
// Every worker has them, and each job is one SQL statement that is safe to run in several workers at once.
$every = function (string $setting, float $seconds, Closure $job) use ($env): void {
    $seconds = $env->float($setting, $seconds);
    if ($seconds > 0) {
        Tenma::async($job);
        Tenma::setInterval($job, $seconds);
    }
};

// Unconfirmed orders are cancelled and their stock freed (Orders::expireUnconfirmed()), and every open tab of
// every worker renders again: the board drops them, the customer's page says so
$orders = $app->get(Orders::class);
$broadcast = $app->get(Broadcast::class);
$every('EXPIRY_INTERVAL', 30, function () use ($orders, $broadcast, $env) {
    if ($orders->expireUnconfirmed($env->int('UNCONFIRMED_MINUTES', 15)) > 0) {
        $broadcast->changed();
    }
});

// The sales for the charts, at most once per interval for all workers (SalesReport::aggregate())
$sales = $app->get(SalesReport::class);
$every('SALES_INTERVAL', 300, fn () => $sales->aggregate($env->float('SALES_INTERVAL', 300)));

// The product page and the pickup days read the holidays and the weather from this worker's memory (LastKnown)
$lastKnown = $app->get(LastKnown::class);
$every('HOLIDAYS_INTERVAL', 86400, $lastKnown->refreshHolidays(...));
$every('WEATHER_INTERVAL', 900, $lastKnown->refreshWeather(...));

$app->serve();

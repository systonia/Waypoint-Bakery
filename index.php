<?php
// The bakery under Tenma: tenma --watch index.php (development) or tenma --workers auto index.php.
// This script runs once per worker. What it sets up here stays in memory and serves every request.

use Bakery\Backoffice\SalesReport;
use Bakery\Ordering\{OrderFeed, Orders};
use Waypoint\Environment;
use Waypoint\Logger;

$app = require __DIR__ . '/app.php';
$env = $app->get(Environment::class);
$log = $app->get(Logger::class);

// Order changes from any worker reach this worker's open tabs (Tenma::publish/subscribe, see OrderFeed)
$feed = $app->get(OrderFeed::class);
$feed->listen();

// Background jobs in the same process. Every worker runs this script, so every worker has these timers; the jobs
// are written so that it doesn't matter (see Orders::expireUnconfirmed() and SalesReport::aggregate()).
$expiry = (float) $env->get('EXPIRY_INTERVAL', 30);
if ($expiry > 0) {
    $orders = $app->get(Orders::class);
    $minutes = (int) $env->get('UNCONFIRMED_MINUTES', 15);
    Tenma::setInterval(function () use ($orders, $feed, $minutes, $log) {
        try {
            foreach ($orders->expireUnconfirmed($minutes) as $id) {
                $feed->announce($id);
            }
        } catch (RuntimeException $e) {
            $log->error('expiring orders failed: ' . $e->getMessage());
        }
    }, $expiry);
}
$salesInterval = (float) $env->get('SALES_INTERVAL', 300);
if ($salesInterval > 0) {
    $sales = $app->get(SalesReport::class);
    $aggregate = function () use ($sales, $salesInterval, $log) {
        try {
            $sales->aggregate($salesInterval);
        } catch (RuntimeException $e) {
            $log->error('aggregating sales failed: ' . $e->getMessage());
        }
    };
    Tenma::async($aggregate);
    Tenma::setInterval($aggregate, $salesInterval);
}

$app->serve();

<?php
// What Tenma::all() saves on the product page. Loads the page's four sources (product and opening hours from
// Postgres, holidays and weather from their web services) side by side and one after another, in turns, and
// prints the median of each:
//   tenma bin/bench.php [rounds = 10] [product = croissant]

use Bakery\Catalog\Products;
use Bakery\Pickup\{Holidays, OpeningHours, Weather};

$app = require __DIR__ . '/../app.php';
$rounds = max(1, (int) ($argv[1] ?? 10));
$slug = $argv[2] ?? 'croissant';
$sources = [
    'product' => fn () => $app->get(Products::class)?->bySlug($slug),
    'hours' => fn () => $app->get(OpeningHours::class)?->week(),
    'holidays' => fn () => $app->get(Holidays::class)?->upcoming(),
    'weather' => fn () => $app->get(Weather::class)?->week(),
];

Tenma::async(function () use ($sources, $rounds): void {
    $milliseconds = function (Closure $work): float {
        $started = hrtime(true);
        $work();
        return (hrtime(true) - $started) / 1e6;
    };
    $median = function (array $values): float {
        sort($values);
        return $values[intdiv(count($values), 2)];
    };
    $together = $apart = [];
    for ($i = 0; $i < $rounds; $i++) {
        $together[] = $milliseconds(fn () => Tenma::all(...$sources));
        $apart[] = $milliseconds(function () use ($sources): void {
            foreach ($sources as $load) {
                $load();
            }
        });
    }
    printf("%d rounds, median\n  with Tenma::all():    %6.1f ms\n  one after another:    %6.1f ms\n", $rounds, $median($together), $median($apart));
    exit(0);   // the app's subscriptions would keep the script running
});

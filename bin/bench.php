<?php
// The product page's two versions, measured against the real services. The page needs four sources: product and
// opening hours from Postgres, holidays and weather from their web services. Three ways to load them, in turns,
// with the median of each:
//   cached:            holidays and weather from memory (LastKnown, loaded once before), Postgres with Tenma::all()
//   live, together:    all four with Tenma::all(), as /products/<slug>?live does
//   live, one by one:  all four one after another
//   tenma bin/bench.php [rounds = 10] [product = croissant]

use Bakery\Catalog\Products;
use Bakery\Pickup\{Holidays, LastKnown, OpeningHours, Weather};

$app = require __DIR__ . '/../app.php';
$rounds = max(1, (int) ($argv[1] ?? 10));
$slug = $argv[2] ?? 'croissant';
$lastKnown = $app->get(LastKnown::class);
$database = [
    'product' => fn () => $app->get(Products::class)?->bySlug($slug),
    'hours' => fn () => $app->get(OpeningHours::class)?->week(),
];
$sources = $database + [
    'holidays' => fn () => $app->get(Holidays::class)?->upcoming(),
    'weather' => fn () => $app->get(Weather::class)?->week(),
];

Tenma::async(function () use ($database, $sources, $lastKnown, $rounds): void {
    $milliseconds = function (Closure $work): float {
        $started = hrtime(true);
        $work();
        return (hrtime(true) - $started) / 1e6;
    };
    $median = function (array $values): float {
        sort($values);
        return $values[intdiv(count($values), 2)];
    };
    $lastKnown->refreshHolidays();
    $lastKnown->refreshWeather();
    $cached = $together = $apart = [];
    for ($i = 0; $i < $rounds; $i++) {
        $cached[] = $milliseconds(fn () => [Tenma::all(...$database), $lastKnown->holidays, $lastKnown->weather]);
        $together[] = $milliseconds(fn () => Tenma::all(...$sources));
        $apart[] = $milliseconds(function () use ($sources): void {
            foreach ($sources as $load) {
                $load();
            }
        });
    }
    printf(
        "%d rounds, median\n  cached:            %6.1f ms\n  live, together:    %6.1f ms\n  live, one by one:  %6.1f ms\n",
        $rounds, $median($cached), $median($together), $median($apart),
    );
    exit(0);   // the app's subscriptions would keep the script running
});

<?php

namespace Bakery\Tests\Support;

use DateTimeImmutable;
use Tenma\Http\{Request, Response};

/**
 * Answers like date.nager.at and api.open-meteo.com, under a prefix that sets how: /fast/..., /slow/... (300 ms
 * late, like the real services on a bad day), /down/... (503) or /broken/... (200 with no JSON). Counts the requests it answers, and the tests may
 * change the forecast's temperature to see a refresh arrive.
 */
final class ApiStub
{
    public static int $calls = 0;

    public static float $temperature = 21.4;

    /** The holiday the fake knows: the day after tomorrow, so it falls among the pickup days. */
    public static function holiday(): string
    {
        return new DateTimeImmutable('today')->modify('+2 days')->format('Y-m-d');
    }

    public function __invoke(Request $request): Response
    {
        self::$calls++;
        [, $mode, $path] = explode('/', $request->path, 3) + [2 => ''];
        if ($mode === 'down') {
            return new Response('overloaded', 503);
        }
        if ($mode === 'broken') {
            return new Response('<html>maintenance</html>', 200);
        }
        if ($mode === 'slow') {
            \Tenma::sleep(0.3);
        }
        if (str_starts_with($path, 'api/v3/NextPublicHolidays/')) {
            return Response::json([
                ['date' => self::holiday(), 'localName' => 'Backtag', 'name' => 'Baking Day', 'global' => true, 'counties' => null],
                ['date' => '2099-01-01', 'localName' => 'Anderswo', 'name' => 'Elsewhere', 'global' => false, 'counties' => ['DE-BY']],
            ]);
        }
        if ($path === 'v1/forecast') {
            $days = array_map(fn (int $i) => new DateTimeImmutable('today')->modify("+$i days")->format('Y-m-d'), range(0, 6));
            return Response::json(['daily' => [
                'time' => $days,
                'weather_code' => array_fill(0, 7, 0),
                'temperature_2m_max' => array_fill(0, 7, self::$temperature),
                'precipitation_probability_max' => array_fill(0, 7, 10),
            ]]);
        }
        return new Response('not found', 404);
    }
}

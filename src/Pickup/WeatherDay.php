<?php

namespace Bakery\Pickup;

/** One day's forecast: the WMO weather code, the highest temperature in °C and the chance of rain in percent. */
final readonly class WeatherDay
{
    public function __construct(
        public int $code,
        public float $max,
        public int $rain,
    ) {
    }
}

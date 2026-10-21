<?php

namespace Bakery\Services;

use Bakery\Models\WeatherDay;
use JsonException;
use RuntimeException;
use Waypoint\Attributes\Inject;
use Waypoint\Environment;
use Waypoint\Logger;
use Waypoint\Support\Row;

/** The week's forecast at the bakery, from api.open-meteo.com (no key needed). */
final class Weather
{
    #[Inject]
    private Environment $env;

    #[Inject]
    private Logger $logger;

    /** @return array<string, WeatherDay>|null Y-m-d => the forecast; null when unavailable */
    public function week(): ?array
    {
        $url = rtrim($this->env->string('WEATHER_URL', 'https://api.open-meteo.com'), '/') . '/v1/forecast?' . http_build_query([
            'latitude' => $this->env->float('BAKERY_LATITUDE'),
            'longitude' => $this->env->float('BAKERY_LONGITUDE'),
            'daily' => 'weather_code,temperature_2m_max,precipitation_probability_max',
            'timezone' => date_default_timezone_get(),
            'forecast_days' => 7,
        ]);
        try {
            $response = new \Http\Client($this->env->float('API_TIMEOUT', 2.5))->get($url);
            if ($response->status !== 200) {
                return null;
            }
            $daily = Row::of($response->json())->row('daily');
            $codes = $daily->list('weather_code', 'int');
            $max = $daily->list('temperature_2m_max', 'float');
            $rain = $daily->list('precipitation_probability_max', 'int');
            $week = [];
            foreach ($daily->list('time', 'string') as $i => $day) {
                $week[$day] = new WeatherDay($codes[$i] ?? 0, $max[$i] ?? 0.0, $rain[$i] ?? 0);
            }
            return $week;
        } catch (RuntimeException|JsonException $e) {
            // UnexpectedValueException, a RuntimeException, is an answer of another shape
            $this->logger->warning('weather: ' . $e->getMessage());
            return null;
        }
    }
}

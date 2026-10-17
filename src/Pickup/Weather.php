<?php

namespace Bakery\Pickup;

use JsonException;
use RuntimeException;
use Waypoint\Attributes\Inject;
use Waypoint\Environment;
use Waypoint\Logger;

/** The week's forecast at the bakery, from api.open-meteo.com (no key needed). */
final class Weather
{
    #[Inject]
    private Environment $env;

    #[Inject]
    private Logger $logger;

    /** @return array<string, array{code: int, max: float, rain: int}>|null Y-m-d => weather code, highest temperature, chance of rain; null when unavailable */
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
            $response = \Tenma::fetch($url, timeout: $this->env->float('API_TIMEOUT', 2.5));
            $daily = $response->status === 200 ? ($response->json()['daily'] ?? null) : null;
        } catch (RuntimeException|JsonException $e) {
            $daily = null;
            $this->logger->warning('weather: ' . $e->getMessage());
        }
        if (!is_array($daily) || !is_array($daily['time'] ?? null)) {
            return null;
        }
        $week = [];
        foreach ($daily['time'] as $i => $day) {
            $week[(string) $day] = [
                'code' => (int) ($daily['weather_code'][$i] ?? 0),
                'max' => (float) ($daily['temperature_2m_max'][$i] ?? 0),
                'rain' => (int) ($daily['precipitation_probability_max'][$i] ?? 0),
            ];
        }
        return $week;
    }
}

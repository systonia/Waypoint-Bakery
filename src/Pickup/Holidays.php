<?php

namespace Bakery\Pickup;

use RuntimeException;
use Waypoint\Attributes\Inject;
use Waypoint\Environment;
use Waypoint\Logger;

/** Public holidays of the bakery's region for the coming year, from date.nager.at (no key needed). */
final class Holidays
{
    #[Inject]
    private Environment $env;

    #[Inject]
    private Logger $logger;

    /**
     * Waits only for this request: while the answer travels, the worker serves others. Null when the service is
     * down, slow or answers nonsense; the shop then carries on without knowing the holidays.
     *
     * @return array<string, array{en: string, de: string}>|null Y-m-d => the holiday's name
     */
    public function upcoming(): ?array
    {
        $url = rtrim((string) $this->env->get('HOLIDAYS_URL', 'https://date.nager.at'), '/') . '/api/v3/NextPublicHolidays/' . rawurlencode((string) $this->env->get('HOLIDAY_COUNTRY', 'DE'));
        try {
            $response = \Tenma::fetch($url, timeout: (float) $this->env->get('API_TIMEOUT', 2.5));
            $list = $response->status === 200 ? $response->json() : null;
        } catch (RuntimeException $e) {
            $list = null;
            $this->logger->warning('holidays: ' . $e->getMessage());
        }
        if (!is_array($list)) {
            return null;
        }
        $region = (string) $this->env->get('HOLIDAY_REGION', '');
        $holidays = [];
        foreach ($list as $holiday) {
            if (!is_array($holiday) || !is_string($holiday['date'] ?? null)) {
                continue;
            }
            $counties = $holiday['counties'] ?? null;
            if (($holiday['global'] ?? false) === true || (is_array($counties) && in_array($region, $counties, true))) {
                $holidays[$holiday['date']] = ['en' => (string) ($holiday['name'] ?? ''), 'de' => (string) ($holiday['localName'] ?? '')];
            }
        }
        return $holidays;
    }
}

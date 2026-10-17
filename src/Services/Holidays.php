<?php

namespace Bakery\Services;

use Bakery\Models\Holiday;
use JsonException;
use RuntimeException;
use Waypoint\Attributes\Inject;
use Waypoint\Environment;
use Waypoint\Logger;
use Waypoint\Support\Row;

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
     * @return array<string, Holiday>|null Y-m-d => the holiday
     */
    public function upcoming(): ?array
    {
        $url = rtrim($this->env->string('HOLIDAYS_URL', 'https://date.nager.at'), '/') . '/api/v3/NextPublicHolidays/' . rawurlencode($this->env->string('HOLIDAY_COUNTRY', 'DE'));
        $region = $this->env->string('HOLIDAY_REGION', '');
        try {
            $response = \Tenma::fetch($url, timeout: $this->env->float('API_TIMEOUT', 2.5));
            if ($response->status !== 200) {
                return null;
            }
            $holidays = [];
            foreach (Row::each($response->json()) as $holiday) {
                $counties = $holiday->nullableRow('counties') === null ? [] : $holiday->list('counties', 'string');
                if ($holiday->bool('global') || in_array($region, $counties, true)) {
                    $holidays[$holiday->string('date')] = new Holiday($holiday->string('name'), $holiday->string('localName'));
                }
            }
            return $holidays;
        } catch (RuntimeException|JsonException $e) {
            // UnexpectedValueException, a RuntimeException, is an answer of another shape
            $this->logger->warning('holidays: ' . $e->getMessage());
            return null;
        }
    }
}

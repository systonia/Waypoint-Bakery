<?php

namespace Bakery\Pickup;

use Waypoint\Attributes\Inject;

/**
 * The last good answer of each web service, kept in this worker's memory. Timers in index.php refresh it in the
 * background; the product page and the pickup days read it without waiting. A failed refresh keeps what was there.
 */
final class LastKnown
{
    #[Inject]
    private Holidays $holidayService;

    #[Inject]
    private Weather $weatherService;

    /** @var array<string, array{en: string, de: string}>|null null until the first good answer */
    public private(set) ?array $holidays = null;

    /** @var array<string, array{code: int, max: float, rain: int}>|null null until the first good answer */
    public private(set) ?array $weather = null;

    public function refreshHolidays(): void
    {
        $this->holidays = $this->holidayService->upcoming() ?? $this->holidays;
    }

    public function refreshWeather(): void
    {
        $this->weather = $this->weatherService->week() ?? $this->weather;
    }
}

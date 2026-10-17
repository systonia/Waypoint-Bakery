<?php

namespace Bakery\Pickup;

use DateTimeImmutable;
use Waypoint\Attributes\Inject;

/** Which days an order can be picked up: from tomorrow on, on days the bakery is open and that are no holiday. */
final class PickupCalendar
{
    /** How far ahead an order may be placed. */
    public const DAYS_AHEAD = 7;

    #[Inject]
    private OpeningHours $hours;

    #[Inject]
    private LastKnown $lastKnown;

    /** @return list<string> the coming pickup days, Y-m-d, with the holidays from this worker's memory */
    public function upcoming(): array
    {
        return self::days(new DateTimeImmutable('today'), $this->hours->week(), $this->lastKnown->holidays ?? []);
    }

    /**
     * @param array<int, OpeningDay> $hours ISO weekday => its hours, from OpeningHours::week()
     * @param array<string, Holiday> $holidays Y-m-d => the holiday, from Holidays::upcoming()
     * @return list<string> Y-m-d, at most $count
     */
    public static function days(DateTimeImmutable $today, array $hours, array $holidays, int $count = self::DAYS_AHEAD): array
    {
        $days = [];
        for ($i = 1; $i <= self::DAYS_AHEAD && count($days) < $count; $i++) {
            $day = $today->modify("+$i day");
            if (isset($hours[$day->format('N')]) && !isset($holidays[$day->format('Y-m-d')])) {
                $days[] = $day->format('Y-m-d');
            }
        }
        return $days;
    }
}

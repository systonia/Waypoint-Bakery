<?php

namespace Bakery\Pickup;

use DateTimeImmutable;

/** Which days an order can be picked up: from tomorrow on, on days the bakery is open and that are no holiday. */
final class PickupCalendar
{
    /** How far ahead an order may be placed. */
    public const DAYS_AHEAD = 7;

    /**
     * @param array<int, array{opens: string, closes: string}> $hours from OpeningHours::week()
     * @param array<string, mixed> $holidays Y-m-d => anything, from Holidays::upcoming()
     * @return list<string> Y-m-d, at most $count
     */
    public static function days(DateTimeImmutable $today, array $hours, array $holidays, int $count = self::DAYS_AHEAD): array
    {
        $days = [];
        for ($i = 1; $i <= self::DAYS_AHEAD && count($days) < $count; $i++) {
            $day = $today->modify("+$i day");
            if (isset($hours[(int) $day->format('N')]) && !isset($holidays[$day->format('Y-m-d')])) {
                $days[] = $day->format('Y-m-d');
            }
        }
        return $days;
    }
}

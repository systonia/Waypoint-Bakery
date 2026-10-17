<?php

namespace Bakery\Tests\Services;

use Bakery\Models\{Holiday, OpeningDay};
use Bakery\Services\PickupCalendar;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PickupCalendarTest extends TestCase
{
    /** @return array<int, OpeningDay> */
    private static function weekdays(): array
    {
        $week = [];
        foreach (range(1, 6) as $day) {
            $week[$day] = $day === 6 ? new OpeningDay($day, '07:00', '13:00') : new OpeningDay($day, '06:30', '18:00');
        }
        return $week;
    }

    public function testThePickupDaysStartTomorrowAndSkipSundays(): void
    {
        // Friday 16 October 2026: Saturday, then Monday and Tuesday
        $days = PickupCalendar::days(new DateTimeImmutable('2026-10-16'), self::weekdays(), [], 3);
        $this->assertSame(['2026-10-17', '2026-10-19', '2026-10-20'], $days);
    }

    public function testHolidaysAreNoPickupDays(): void
    {
        $days = PickupCalendar::days(new DateTimeImmutable('2026-10-30'), self::weekdays(), ['2026-11-02' => new Holiday('All Souls', 'Allerseelen')], 2);
        $this->assertSame(['2026-10-31', '2026-11-03'], $days);
    }

    public function testThePickupDaysReachOneWeekAheadAtMost(): void
    {
        $this->assertSame([], PickupCalendar::days(new DateTimeImmutable('2026-10-16'), [], []));
        $this->assertCount(6, PickupCalendar::days(new DateTimeImmutable('2026-10-16'), self::weekdays(), []));
    }
}

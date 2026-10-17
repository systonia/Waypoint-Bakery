<?php

namespace Bakery;

use IntlDateFormatter;
use NumberFormatter;

/** Prices, numbers and days as the customer's language writes them. */
final class Format
{
    public static function price(int $cents, string $locale): string
    {
        return (string) new NumberFormatter($locale === 'de' ? 'de_DE' : 'en_IE', NumberFormatter::CURRENCY)->formatCurrency($cents / 100, 'EUR');
    }

    /** For the charts: "1.234,5" or "1,234.5". */
    public static function number(float $value, int $decimals, string $locale): string
    {
        return $locale === 'de' ? number_format($value, $decimals, ',', '.') : number_format($value, $decimals);
    }

    /** "Fri 17 Oct" or "Fr., 17. Okt." for a Y-m-d date. */
    public static function day(string $date, string $locale): string
    {
        $pattern = $locale === 'de' ? 'EEE, d. MMM' : 'EEE d MMM';
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'UTC', null, $pattern);
        return (string) $formatter->format(new \DateTimeImmutable($date, new \DateTimeZone('UTC')));
    }
}

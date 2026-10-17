<?php

namespace Bakery\Tests;

use Bakery\Pickup\LastKnown;
use Waypoint\Testing\TestResponse;

final class StorefrontTest extends AppTestCase
{
    public function testTheCatalogInEnglishAndGerman(): void
    {
        $this->db()->execute('UPDATE products SET stock = 0 WHERE id = 1');
        $this->get('/')->assertOk()->assertSee('Lye pretzel')->assertSee('€1.20')->assertSee('<app-add-to-cart data-live')
            ->assertSee('Sold out for today')->assertDontSee('>0 left');
        $this->get('/?lang=de')->assertOk()->assertSee('Laugenbrezel')->assertSee("1,20\u{a0}€")->assertSee('In den Warenkorb')
            ->assertCookie('lang', 'de');
    }

    public function testEveryBrowserGetsACartCookie(): void
    {
        $cookie = $this->get('/')->cookie('cart') ?? '';
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $cookie);
        $this->assertNull($this->request('GET', '/')->withCookie('cart', $cookie)->send()->cookie('cart'));
    }

    public function testTheLiveProductPageLoadsItsSourcesAtTheSameTime(): void
    {
        $this->useApis('slow');   // both web services answer after 300 ms
        $response = $this->get('/products/croissant?live')->assertOk()->assertSee('Butter croissant');
        $this->assertSame(2, ApiStub::$calls);
        // Together they waited 600 ms; side by side the page took little more than one of them
        $this->assertLessThan(500, $this->duration($response, 'live'));
    }

    public function testThePickupDaysSkipTheHolidayAndShowTheWeather(): void
    {
        $page = $this->get('/products/pretzel?live')->assertOk()->assertSee('Closed on')->assertSee('Baking Day')->assertSee('☀️ Sunny, 21 °C');
        $this->assertStringNotContainsString(\Bakery\Format::day(ApiStub::holiday(), 'en') . '</span>', $page->body(), 'the holiday is no pickup day');
    }

    public function testTheLiveProductPageCarriesOnWhenTheServicesFail(): void
    {
        foreach (['down', 'broken'] as $mode) {
            $this->useApis($mode);
            $this->get('/products/pretzel?lang=de&live')->assertOk()
                ->assertSee('Laugenbrezel')
                ->assertSee('Die Wettervorhersage ist gerade nicht verfügbar.')
                ->assertSee('Die Feiertage konnten wir gerade nicht prüfen');
        }
    }

    /** What the timers in index.php keep in LastKnown: nothing before the first answer, then the last good one. */
    public function testTheCachedProductPageServesWhatTheTimersLoaded(): void
    {
        $this->get('/products/pretzel')->assertOk()->assertSee('The weather forecast is not available right now.');

        $this->useApis('slow');
        $this->refresh();
        ApiStub::$calls = 0;
        $response = $this->get('/products/pretzel')->assertSee('☀️ Sunny, 21 °C')->assertSee('Baking Day');
        $this->assertLessThan(300, $this->duration($response, 'cached'), 'no wait for the 300 ms services');
        $this->assertSame(0, ApiStub::$calls);

        ApiStub::$temperature = 9.6;
        $this->refresh();
        $this->get('/products/pretzel')->assertSee('☀️ Sunny, 10 °C');

        $this->useApis('broken');
        $this->refresh();
        $this->get('/products/pretzel')->assertSee('☀️ Sunny, 10 °C')->assertSee('Baking Day');
    }

    private function refresh(): void
    {
        $lastKnown = $this->app()->get(LastKnown::class);
        $lastKnown->refreshHolidays();
        $lastKnown->refreshWeather();
    }

    /** The milliseconds Server-Timing reports for $version (cached or live). */
    private function duration(TestResponse $response, string $version): float
    {
        $this->assertSame(1, preg_match("/^$version;dur=([\\d.]+)$/", $response->header('Server-Timing') ?? '', $match));
        return floatval($match[1]);
    }
}

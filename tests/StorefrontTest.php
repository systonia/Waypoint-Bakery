<?php

namespace Bakery\Tests;

use Bakery\Pickup\LastKnown;
use Waypoint\Testing\TestResponse;

final class StorefrontTest extends AppTestCase
{
    public function testTheCatalogInEnglishAndGerman(): void
    {
        $this->get('/')->assertOk()->assertSee('Lye pretzel')->assertSee('€1.20')->assertSee('<app-add-to-cart data-live');
        $this->get('/?lang=de')->assertOk()->assertSee('Laugenbrezel')->assertSee("1,20\u{a0}€")->assertSee('In den Warenkorb')
            ->assertCookie('lang', 'de');
    }

    public function testEveryBrowserGetsACartCookie(): void
    {
        $cookie = (string) $this->get('/')->cookie('cart');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $cookie);
        $this->request('GET', '/')->withCookie('cart', $cookie)->send()->assertOk();
        $this->assertNull($this->request('GET', '/')->withCookie('cart', $cookie)->send()->cookie('cart'));
    }

    public function testTheLiveProductPageLoadsItsSourcesAtTheSameTime(): void
    {
        $this->useApis('slow');   // both web services answer after 300 ms
        $response = $this->get('/products/croissant?live')->assertOk()->assertSee('Butter croissant');
        $ms = $this->timings($response);

        $this->assertSame('live', $ms['version']);
        $this->assertSame(2, ApiStub::$calls);
        $this->assertGreaterThanOrEqual(300, $ms['holidays']);
        $this->assertGreaterThanOrEqual(300, $ms['weather']);
        // Together they waited 600 ms; side by side the page took little more than one of them
        $this->assertLessThan(500, $ms['total']);
    }

    public function testThePickupDaysSkipTheHolidayAndShowTheWeather(): void
    {
        $holiday = ApiStub::holiday();
        $page = $this->get('/products/pretzel?live')->assertOk()->assertSee('Closed on')->assertSee('Baking Day')->assertSee('☀️ Sunny, 21 °C');
        $this->assertStringNotContainsString(
            \Bakery\Format::day($holiday, 'en') . '</span>',
            $page->body(),
            'the holiday is no pickup day',
        );
    }

    public function testTheLiveProductPageCarriesOnWhenTheServicesAreDown(): void
    {
        $this->useApis('down');
        $this->get('/products/pretzel?lang=de&live')->assertOk()
            ->assertSee('Laugenbrezel')
            ->assertSee('Die Wettervorhersage ist gerade nicht verfügbar.')
            ->assertSee('Die Feiertage konnten wir gerade nicht prüfen');
    }

    public function testTheCachedProductPageServesFromMemoryAfterTheFirstLoad(): void
    {
        $this->useApis('slow');
        $this->refresh();
        ApiStub::$calls = 0;

        foreach (['croissant', 'pretzel', 'croissant'] as $slug) {
            $response = $this->get("/products/$slug")->assertOk()->assertSee('☀️ Sunny, 21 °C')->assertSee('Baking Day');
            $ms = $this->timings($response);
            $this->assertSame('cached', $ms['version']);
            $this->assertSame('cache', $ms['holidays']);
            $this->assertSame('cache', $ms['weather']);
            $this->assertLessThan(300, $ms['total'], 'no wait for the 300 ms services');
        }
        $this->assertSame(0, ApiStub::$calls);
    }

    public function testTheCachedProductPageFallsBackUntilTheFirstLoad(): void
    {
        $this->get('/products/pretzel?lang=de')->assertOk()
            ->assertSee('Die Wettervorhersage ist gerade nicht verfügbar.')
            ->assertSee('Die Feiertage konnten wir gerade nicht prüfen');
        $this->assertSame(0, ApiStub::$calls);
    }

    public function testARefreshReplacesTheData(): void
    {
        $this->refresh();
        $this->get('/products/pretzel')->assertSee('☀️ Sunny, 21 °C');

        ApiStub::$temperature = 9.6;
        $this->refresh();
        $this->get('/products/pretzel')->assertSee('☀️ Sunny, 10 °C');
    }

    public function testAFailedRefreshKeepsTheLastGoodData(): void
    {
        $this->refresh();
        $this->useApis('down');
        $this->refresh();
        $this->assertSame(4, ApiStub::$calls);
        $this->get('/products/pretzel')->assertOk()->assertSee('☀️ Sunny, 21 °C')->assertSee('Baking Day')
            ->assertDontSee('The weather forecast is not available right now.');
    }

    public function testAnUnknownProductIsNotFound(): void
    {
        $this->get('/products/baguette')->assertStatus(404);
    }

    /** What the timers in index.php do in the running app. */
    private function refresh(): void
    {
        $lastKnown = $this->service(LastKnown::class);
        $lastKnown->refreshHolidays();
        $lastKnown->refreshWeather();
    }

    /** @return array<string, string|float> Server-Timing: name => duration in ms, or its description */
    private function timings(TestResponse $response): array
    {
        $timings = [];
        foreach (explode(', ', (string) $response->header('Server-Timing')) as $entry) {
            [$name, $value] = explode(';', $entry, 2);
            $timings[$name] = str_starts_with($value, 'dur=') ? (float) substr($value, 4) : substr($value, 5);
        }
        return $timings;
    }
}

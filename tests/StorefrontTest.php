<?php

namespace Bakery\Tests;

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

    public function testTheProductPageLoadsItsSourcesAtTheSameTime(): void
    {
        $this->useApis('slow');   // both web services answer after 300 ms
        $response = $this->get('/products/croissant')->assertOk()->assertSee('Butter croissant');
        preg_match_all('/(\w+);dur=([\d.]+)/', (string) $response->header('Server-Timing'), $timings);
        $ms = array_combine($timings[1], array_map('floatval', $timings[2]));

        $this->assertGreaterThanOrEqual(300, $ms['holidays']);
        $this->assertGreaterThanOrEqual(300, $ms['weather']);
        // Together they waited 600 ms; side by side the page took little more than one of them
        $this->assertLessThan(500, $ms['total']);
    }

    public function testThePickupDaysSkipTheHolidayAndShowTheWeather(): void
    {
        $holiday = ApiStub::holiday();
        $page = $this->get('/products/pretzel')->assertOk()->assertSee('Closed on')->assertSee('Baking Day')->assertSee('☀️ Sunny, 21 °C');
        $this->assertStringNotContainsString(
            \Bakery\Format::day($holiday, 'en') . '</span>',
            $page->body(),
            'the holiday is no pickup day',
        );
    }

    public function testTheProductPageCarriesOnWhenTheServicesAreDown(): void
    {
        $this->useApis('down');
        $this->get('/products/pretzel?lang=de')->assertOk()
            ->assertSee('Laugenbrezel')
            ->assertSee('Die Wettervorhersage ist gerade nicht verfügbar.')
            ->assertSee('Die Feiertage konnten wir gerade nicht prüfen');
    }

    public function testAnUnknownProductIsNotFound(): void
    {
        $this->get('/products/baguette')->assertStatus(404);
    }
}

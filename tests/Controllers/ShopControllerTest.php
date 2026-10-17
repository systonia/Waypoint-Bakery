<?php

namespace Bakery\Tests\Controllers;

use Bakery\Repositories\Orders;
use Bakery\Services\Format;
use Bakery\Tests\Support\{ApiStub, AppTestCase};

/** The storefront's pages: the catalog, the product page and the way to the order page. */
final class ShopControllerTest extends AppTestCase
{
    public function testTheCatalogShowsTheRangeInEnglishAndGerman(): void
    {
        $this->db()->execute('UPDATE products SET stock = 0 WHERE id = 1');
        $this->get('/')->assertOk()->assertSee('Lye pretzel')->assertSee('€1.20')->assertSee('<app-add-to-cart data-live')
            ->assertSee('Sold out for today')->assertDontSee('>0 left');
        $this->get('/?lang=de')->assertOk()->assertSee('Laugenbrezel')->assertSee("1,20\u{a0}€")->assertSee('In den Warenkorb')
            ->assertCookie('lang', 'de');
    }

    public function testTheLiveProductPageAsksItsSourcesAtTheSameTime(): void
    {
        $this->useApis('slow');   // both web services answer after 300 ms
        $response = $this->get('/products/croissant?live')->assertOk()->assertSee('Butter croissant');
        $this->assertSame(2, ApiStub::$calls);
        // Together they waited 600 ms; side by side the page took little more than one of them
        $this->assertLessThan(500, $this->serverTiming($response, 'live'));
    }

    public function testThePickupDaysSkipTheHolidayAndShowTheWeather(): void
    {
        $page = $this->get('/products/pretzel?live')->assertOk()->assertSee('Closed on')->assertSee('Baking Day')->assertSee('☀️ Sunny, 21 °C');
        $this->assertStringNotContainsString(Format::day(ApiStub::holiday(), 'en') . '</span>', $page->body(), 'the holiday is no pickup day');
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

    public function testTheThanksPageLeavesAMessageForTheOrderPage(): void
    {
        $code = $this->app()->get(Orders::class)->place([4 => 2], 'Mia', 'mia@example.test', $this->pickupDay(), 'en')->code;
        $thanks = $this->get("/orders/$code/thanks")->assertRedirect("/orders/$code", 303);
        $this->request('GET', "/orders/$code")->withCookie('flash', $thanks->cookie('flash') ?? '')->send()
            ->assertOk()
            ->assertSee('Thank you, Mia! Your order is reserved.')
            ->assertSee('<app-order-tracker data-live');
    }
}

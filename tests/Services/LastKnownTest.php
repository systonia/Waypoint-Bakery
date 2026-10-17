<?php

namespace Bakery\Tests\Services;

use Bakery\Services\LastKnown;
use Bakery\Tests\Support\{ApiStub, AppTestCase};

/** What the timers in index.php keep in LastKnown, as the product page shows it. */
final class LastKnownTest extends AppTestCase
{
    public function testTheProductPageServesNothingBeforeTheFirstAnswerThenTheLastGoodOne(): void
    {
        $this->get('/products/pretzel')->assertOk()->assertSee('The weather forecast is not available right now.');

        $this->useApis('slow');
        $this->refresh();
        ApiStub::$calls = 0;
        $response = $this->get('/products/pretzel')->assertSee('☀️ Sunny, 21 °C')->assertSee('Baking Day');
        $this->assertLessThan(300, $this->serverTiming($response, 'cached'), 'no wait for the 300 ms services');
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
}

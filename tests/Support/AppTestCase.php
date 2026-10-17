<?php

namespace Bakery\Tests\Support;

use Bakery\Services\PickupCalendar;
use Bakery\Tasks\DatabaseTasks;
use Tenma\Db\Postgres;
use Waypoint\Environment;
use Waypoint\Http\Request;
use Waypoint\Live\Broadcast;
use Waypoint\Live\Testing\Tab;
use Waypoint\Testing\{TestCase, TestResponse};

/**
 * The app as app.php builds it, on the test database (config.test.json) with fresh demo data for every test,
 * and the web services faked by ApiStub ($mode: fast, slow, down or broken).
 */
abstract class AppTestCase extends TestCase
{
    protected const OWNER = ['sub' => 1, 'name' => 'Anna', 'role' => 'owner'];

    protected const STAFF = ['sub' => 2, 'name' => 'Ben', 'role' => 'staff'];

    /** The cart cookie of the customer's browser in customerTab(). */
    protected const CART = '0b3c2a1e-5d4f-4a8b-9c7d-6e5f4a3b2c1d';

    protected function setUp(): void
    {
        parent::setUp();
        ApiStub::$calls = 0;
        ApiStub::$temperature = 21.4;
        require __DIR__ . '/../../app.php';
        $this->useApis('fast');
        $this->app()->get(DatabaseTasks::class)->reset();
    }

    protected function useApis(string $mode): void
    {
        $env = $this->app()->get(Environment::class);
        $env->set('HOLIDAYS_URL', getenv('API_STUB') . "/$mode");
        $env->set('WEATHER_URL', getenv('API_STUB') . "/$mode");
    }

    /**
     * The first column of the first row of a SELECT.
     *
     * @param list<mixed> $params
     */
    protected function sql(string $sql, array $params = []): mixed
    {
        $row = $this->db()->first($sql, $params);
        return $row === null ? null : reset($row);
    }

    /** What the app does after an order changed. The broadcast travels through Tenma::publish(), so it takes a moment. */
    protected function broadcast(): void
    {
        $this->app()->get(Broadcast::class)->changed();
        $this->settle();
    }

    /** Lets broadcasts that are on their way reach the tabs. */
    protected function settle(): void
    {
        \Tenma::sleep(0.2);
    }

    protected function db(): Postgres
    {
        return $this->app()->get(Postgres::class);
    }

    /** A tab of the customer's browser, with its cart cookie. */
    protected function customerTab(): Tab
    {
        return Tab::for($this->app(), Request::fromParts('GET', '/_live', cookies: ['cart' => self::CART]));
    }

    /**
     * A tab of someone signed in to the back office.
     *
     * @param array<string, mixed> $user
     */
    protected function staffTab(array $user): Tab
    {
        $request = Request::fromParts('GET', '/_live');
        $request->jwt = $user;
        return Tab::for($this->app(), $request);
    }

    /** The first pickup day. */
    protected function pickupDay(): string
    {
        return $this->app()->get(PickupCalendar::class)->upcoming()[0];
    }

    /** The milliseconds Server-Timing reports for $version (cached or live). */
    protected function serverTiming(TestResponse $response, string $version): float
    {
        $this->assertSame(1, preg_match("/^$version;dur=([\\d.]+)$/", $response->header('Server-Timing') ?? '', $match));
        return floatval($match[1]);
    }
}

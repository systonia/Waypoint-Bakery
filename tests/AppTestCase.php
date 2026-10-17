<?php

namespace Bakery\Tests;

use Bakery\Database\DatabaseTasks;
use Tenma\Db\Postgres;
use Waypoint\Environment;
use Waypoint\Live\Broadcast;
use Waypoint\Testing\TestCase;

/**
 * The app as app.php builds it, on the test database (config.test.json) with fresh demo data for every test,
 * and the web services faked by ApiStub ($mode: fast, slow, down or broken).
 */
abstract class AppTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ApiStub::$calls = 0;
        ApiStub::$temperature = 21.4;
        require __DIR__ . '/../app.php';
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
}

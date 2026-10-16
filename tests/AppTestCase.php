<?php

namespace Bakery\Tests;

use Bakery\Database\DatabaseTasks;
use Tenma\Db\Postgres;
use Waypoint\Environment;
use Waypoint\Testing\TestCase;

/**
 * The app as app.php builds it, on the test database (config.test.json) with fresh demo data for every test,
 * and the web services faked by ApiStub ($mode: fast, slow or down).
 */
abstract class AppTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useApis('fast');
        require __DIR__ . '/../app.php';
        $this->service(DatabaseTasks::class)->reset();
    }

    protected function useApis(string $mode): void
    {
        $env = $this->service(Environment::class);
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

    protected function db(): Postgres
    {
        return $this->service(Postgres::class);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected function service(string $class): object
    {
        return $this->app()->get($class) ?? throw new \LogicException("No $class in the app");
    }
}

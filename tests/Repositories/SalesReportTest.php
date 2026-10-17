<?php

namespace Bakery\Tests\Repositories;

use Bakery\Repositories\SalesReport;
use Bakery\Tests\Support\AppTestCase;

final class SalesReportTest extends AppTestCase
{
    public function testTheJobAggregatesThePickedUpOrdersOncePerInterval(): void
    {
        $sales = $this->app()->get(SalesReport::class);
        $this->assertGreaterThan(0, $sales->aggregate(300));
        $this->assertSame(0, $sales->aggregate(300), 'another worker just ran it');
        $this->assertSame(
            $this->sql("SELECT sum(quantity) FROM order_lines JOIN orders ON orders.id = order_id WHERE status = 'picked_up'"),
            $this->sql('SELECT sum(quantity) FROM daily_sales'),
        );
    }
}

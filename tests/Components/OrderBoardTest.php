<?php

namespace Bakery\Tests\Components;

use Bakery\Repositories\Orders;
use Bakery\Tests\Support\AppTestCase;
use Waypoint\Exceptions\UnauthorizedException;
use Waypoint\Live\Testing\Tab;

final class OrderBoardTest extends AppTestCase
{
    public function testANewOrderAppearsOnEveryOpenBoard(): void
    {
        $morning = $this->staffTab(self::STAFF)->mount('app-order-board');
        $office = $this->staffTab(self::OWNER)->mount('app-order-board');
        $morning->assertDontSee('Lena');

        $orders = $this->app()->get(Orders::class);
        $order = $orders->place([4 => 3], 'Lena', 'lena@example.test', date('Y-m-d', strtotime('tomorrow')), 'en');
        $this->broadcast();   // what the checkout does
        $morning->assertSee('Lena')->assertSee('3 × Butter croissant')->assertSee('Not confirmed yet');
        $office->assertSee('Lena');

        // A confirmed order can be moved on from either board, and both follow
        $orders->confirm($order->id);
        $this->broadcast();
        $morning->click('li[data-key="' . $order->code . '"] button');
        $this->settle();
        $this->assertSame('baking', $this->sql('SELECT status FROM orders WHERE id = $1', [$order->id]));
        $this->assertMatchesRegularExpression('/data-key="baking".*data-key="' . $order->code . '"/s', $office->html());
    }

    public function testTheBoardIsOnlyForSignedInStaff(): void
    {
        $this->expectException(UnauthorizedException::class);
        Tab::for($this->app())->mount('app-order-board');
    }
}

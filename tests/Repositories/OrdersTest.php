<?php

namespace Bakery\Tests\Repositories;

use Bakery\Models\{OrderStatus, OutOfStock};
use Bakery\Repositories\Orders;
use Bakery\Tests\Support\AppTestCase;

final class OrdersTest extends AppTestCase
{
    public function testTheLastPieceGoesToOnlyOneCustomer(): void
    {
        $this->db()->execute('UPDATE products SET stock = 1 WHERE id = 6');
        $orders = $this->app()->get(Orders::class);
        $this->assertSame(OrderStatus::Pending, $orders->place([6 => 1], 'Mia', 'mia@example.test', $this->pickupDay(), 'en')->status);
        $this->expectException(OutOfStock::class);
        $orders->place([6 => 1, 2 => 1], 'Noah', 'noah@example.test', $this->pickupDay(), 'en');
    }

    public function testUnconfirmedOrdersExpireOnceAndGiveTheirStockBack(): void
    {
        $orders = $this->app()->get(Orders::class);
        $order = $orders->place([5 => 3], 'Noah', 'noah@example.test', $this->pickupDay(), 'en');
        $this->assertSame(21, $this->sql('SELECT stock FROM products WHERE id = 5'));

        $this->assertSame(0, $orders->expireUnconfirmed(15), 'too early');
        $this->db()->execute("UPDATE orders SET created_at = now() - interval '16 minutes' WHERE id = $1", [$order->id]);
        $this->assertSame(1, $orders->expireUnconfirmed(15));
        $this->assertSame(0, $orders->expireUnconfirmed(15), 'only once');
        $this->assertSame(24, $this->sql('SELECT stock FROM products WHERE id = 5'));
        $this->assertSame('cancelled', $this->sql('SELECT status FROM orders WHERE id = $1', [$order->id]));
    }
}

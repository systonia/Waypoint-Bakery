<?php

namespace Bakery\Tests\Components;

use Bakery\Models\OrderStatus;
use Bakery\Repositories\Orders;
use Bakery\Tests\Support\AppTestCase;

final class OrderTrackerTest extends AppTestCase
{
    public function testTheOrderPageFollowsTheBakery(): void
    {
        $orders = $this->app()->get(Orders::class);
        $order = $orders->place([1 => 1], 'Mia', 'mia@example.test', $this->pickupDay(), 'en');
        $tracker = $this->customerTab()->mount('app-order-tracker', ['code' => $order->code])->assertSee('Please confirm the order');

        $tracker->click('confirm')->assertSee('Received')->assertDontSee('Please confirm');

        // The bakery moves it on somewhere else: here, the API
        $orders->advance($order->id, OrderStatus::Received);
        $this->broadcast();
        $this->assertMatchesRegularExpression('/aria-current="step"\s*>\s*In the oven/', $tracker->html());
    }
}

<?php

namespace Bakery\Responses;

use Bakery\Models\{Order, OrderLine, OrderStatus};
use Waypoint\Attributes\ListOf;

/** An order as the API shows it: without the customer's email and the internal id. */
final readonly class OrderSummary
{
    public function __construct(
        public string $code,
        public string $customer,
        public string $pickupDate,
        public OrderStatus $status,
        public int $totalCents,
        #[ListOf(OrderLine::class)]
        public array $lines,
    ) {
    }

    public static function of(Order $order): self
    {
        return new self($order->code, $order->customerName, $order->pickupDate, $order->status, $order->totalCents(), $order->lines);
    }
}

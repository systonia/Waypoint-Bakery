<?php

namespace Bakery\Api;

use Bakery\Ordering\{Order, OrderLine, OrderStatus};

/** An order as the API shows it: without the customer's email and the internal id. */
final readonly class OrderSummary
{
    /** @param list<OrderLine> $lines */
    public function __construct(
        public string $code,
        public string $customer,
        public string $pickupDate,
        public OrderStatus $status,
        public int $totalCents,
        public array $lines,
    ) {
    }

    public static function of(Order $order): self
    {
        return new self($order->code, $order->customerName, $order->pickupDate, $order->status, $order->totalCents(), $order->lines);
    }
}

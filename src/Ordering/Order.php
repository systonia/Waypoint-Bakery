<?php

namespace Bakery\Ordering;

final readonly class Order
{
    /** @param list<OrderLine> $lines */
    public function __construct(
        public int $id,
        public string $code,
        public string $customerName,
        public string $email,
        public string $pickupDate,
        public string $locale,
        public OrderStatus $status,
        public string $createdAt,
        public array $lines,
    ) {
    }

    public function totalCents(): int
    {
        return array_sum(array_map(fn (OrderLine $line): int => $line->totalCents(), $this->lines));
    }
}

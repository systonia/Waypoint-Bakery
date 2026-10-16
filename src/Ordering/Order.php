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
        public int $totalCents,
        public string $createdAt,
        public array $lines,
    ) {
    }

    /** @param array<string, mixed> $row an orders row with its lines as JSON in `lines` */
    public static function fromRow(array $row): self
    {
        $lines = [];
        foreach ((array) $row['lines'] as $line) {
            $lines[] = new OrderLine((int) $line['product'], (string) $line['name'], (int) $line['price'], (int) $line['quantity']);
        }
        return new self(
            (int) $row['id'],
            (string) $row['code'],
            (string) $row['customer_name'],
            (string) $row['email'],
            (string) $row['pickup_date'],
            (string) $row['locale'],
            OrderStatus::from((string) $row['status']),
            (int) $row['total_cents'],
            (string) $row['created_at'],
            $lines,
        );
    }
}

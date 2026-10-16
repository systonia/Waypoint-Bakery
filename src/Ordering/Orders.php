<?php

namespace Bakery\Ordering;

use DomainException;
use Tenma\Db\Postgres;
use Waypoint\Attributes\Inject;

/** Orders in Postgres: placing one reserves its stock, cancelling one gives the stock back. */
final class Orders
{
    private const SELECT = <<<'SQL'
        SELECT o.*, coalesce((
            SELECT json_agg(json_build_object('product', product_id, 'name', name, 'price', price_cents, 'quantity', quantity) ORDER BY name)
            FROM order_lines WHERE order_id = o.id
        ), '[]') AS lines
        FROM orders o
        SQL;

    #[Inject]
    private Postgres $db;

    /**
     * Reserves the stock and stores the order as pending, in one transaction: if one product is short, nothing is
     * reserved and OutOfStock names it.
     *
     * @param array<int, int> $quantities product id => quantity
     */
    public function place(array $quantities, string $name, string $email, string $pickupDate, string $locale): Order
    {
        $id = $this->db->transaction(function (Postgres $tx) use ($quantities, $name, $email, $pickupDate, $locale): int {
            $order = $tx->first(
                'INSERT INTO orders (code, customer_name, email, pickup_date, locale, total_cents) VALUES ($1, $2, $3, $4, $5, 0) RETURNING id',
                [bin2hex(random_bytes(5)), $name, $email, $pickupDate, $locale],
            ) ?? throw new DomainException('The order was not stored');
            $id = (int) $order['id'];
            ksort($quantities);   // the same order of row locks in every transaction: no deadlocks between two checkouts
            foreach ($quantities as $product => $quantity) {
                $reserved = $tx->first(
                    'UPDATE products SET stock = stock - $1 WHERE id = $2 AND stock >= $1 RETURNING name ->> $3 AS name, price_cents',
                    [$quantity, $product, $locale],
                );
                if ($reserved === null) {
                    $short = $tx->first('SELECT name ->> $2 AS name FROM products WHERE id = $1', [$product, $locale]);
                    throw new OutOfStock((string) ($short['name'] ?? $product));
                }
                $tx->execute(
                    'INSERT INTO order_lines (order_id, product_id, name, price_cents, quantity) VALUES ($1, $2, $3, $4, $5)',
                    [$id, $product, $reserved['name'], $reserved['price_cents'], $quantity],
                );
            }
            $tx->execute('UPDATE orders SET total_cents = (SELECT sum(price_cents * quantity) FROM order_lines WHERE order_id = $1) WHERE id = $1', [$id]);
            return $id;
        });
        return $this->byId($id) ?? throw new DomainException("Order $id is gone");
    }

    public function byId(int $id): ?Order
    {
        $row = $this->db->first(self::SELECT . ' WHERE o.id = $1', [$id]);
        return $row === null ? null : Order::fromRow($row);
    }

    public function byCode(string $code): ?Order
    {
        $row = $this->db->first(self::SELECT . ' WHERE o.code = $1', [$code]);
        return $row === null ? null : Order::fromRow($row);
    }

    /** @return list<Order> everything not picked up or cancelled yet, oldest pickup first */
    public function open(): array
    {
        return array_map(Order::fromRow(...), $this->db->query(self::SELECT . " WHERE o.status NOT IN ('picked_up', 'cancelled') ORDER BY o.pickup_date, o.id"));
    }

    /** @return list<Order> placed in the last $days days, newest first */
    public function recent(int $days): array
    {
        return array_map(Order::fromRow(...), $this->db->query(self::SELECT . ' WHERE o.created_at > now() - make_interval(days => $1) ORDER BY o.id DESC', [$days]));
    }

    /** The customer confirms a pending order; false when it was not pending (any more). */
    public function confirm(int $id): bool
    {
        return $this->move($id, OrderStatus::Pending, OrderStatus::Received);
    }

    /** The bakery takes the next step; false when the order is no longer where the caller saw it. */
    public function advance(int $id, OrderStatus $from): bool
    {
        $to = $from->next() ?? throw new DomainException("An order that is {$from->value} has no next step");
        return $this->move($id, $from, $to);
    }

    /**
     * Cancels pending orders older than $minutes and puts their stock back. The UPDATE locks the rows it changes,
     * so when several workers run this at the same moment, the second one waits, then finds those orders cancelled
     * already: each order is cancelled and restocked exactly once.
     *
     * @return list<int> the ids of the cancelled orders
     */
    public function expireUnconfirmed(int $minutes): array
    {
        return $this->db->transaction(function (Postgres $tx) use ($minutes): array {
            $ids = array_map(fn (array $row): int => (int) $row['id'], $tx->query(
                "UPDATE orders SET status = 'cancelled', updated_at = now()
                 WHERE status = 'pending' AND created_at < now() - make_interval(mins => $1)
                 RETURNING id",
                [$minutes],
            ));
            if ($ids !== []) {
                $tx->execute(
                    'UPDATE products p SET stock = p.stock + freed.quantity
                     FROM (SELECT product_id, sum(quantity) AS quantity FROM order_lines WHERE order_id = ANY($1::int[]) GROUP BY product_id) AS freed
                     WHERE p.id = freed.product_id',
                    ['{' . implode(',', $ids) . '}'],
                );
            }
            return $ids;
        });
    }

    /** A compare-and-set on the status: a second click, or a second member of staff, changes nothing. */
    private function move(int $id, OrderStatus $from, OrderStatus $to): bool
    {
        return $this->db->execute(
            'UPDATE orders SET status = $3, updated_at = now() WHERE id = $1 AND status = $2',
            [$id, $from->value, $to->value],
        ) === 1;
    }
}

<?php

namespace Bakery\Ordering;

use DomainException;
use Tenma\Db\Postgres;
use Waypoint\Attributes\Inject;

/** Orders in Postgres: placing one reserves its stock, cancelling one gives the stock back. */
final class Orders
{
    // The columns under the names of Order's and OrderLine's constructors
    private const SELECT = <<<'SQL'
        SELECT o.id, o.code, o.customer_name AS "customerName", o.email, o.pickup_date AS "pickupDate", o.locale,
            o.status, o.created_at AS "createdAt", coalesce((
                SELECT json_agg(json_build_object('product', product_id, 'name', name, 'priceCents', price_cents, 'quantity', quantity) ORDER BY name)
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
            $id = $tx->first(
                'INSERT INTO orders (code, customer_name, email, pickup_date, locale) VALUES ($1, $2, $3, $4, $5) RETURNING id',
                [bin2hex(random_bytes(5)), $name, $email, $pickupDate, $locale],
            )['id'] ?? throw new DomainException('The order was not stored');
            ksort($quantities);   // the same order of row locks in every transaction: no deadlocks between two checkouts
            foreach ($quantities as $product => $quantity) {
                $reserved = $tx->first(
                    'UPDATE products SET stock = stock - $1 WHERE id = $2 AND stock >= $1 RETURNING name ->> $3 AS name, price_cents',
                    [$quantity, $product, $locale],
                ) ?? throw new OutOfStock($tx->first('SELECT name ->> $2 AS name FROM products WHERE id = $1', [$product, $locale])['name'] ?? "#$product");
                $tx->execute(
                    'INSERT INTO order_lines (order_id, product_id, name, price_cents, quantity) VALUES ($1, $2, $3, $4, $5)',
                    [$id, $product, $reserved['name'], $reserved['price_cents'], $quantity],
                );
            }
            return $id;
        });
        return $this->byId($id) ?? throw new DomainException("Order $id is gone");
    }

    public function byId(int $id): ?Order
    {
        $row = $this->db->first(self::SELECT . ' WHERE o.id = $1', [$id]);
        return $row === null ? null : self::order($row);
    }

    public function byCode(string $code): ?Order
    {
        $row = $this->db->first(self::SELECT . ' WHERE o.code = $1', [$code]);
        return $row === null ? null : self::order($row);
    }

    /** @return list<Order> everything not picked up or cancelled yet, oldest pickup first */
    public function open(): array
    {
        return array_map(self::order(...), $this->db->query(self::SELECT . " WHERE o.status NOT IN ('picked_up', 'cancelled') ORDER BY o.pickup_date, o.id"));
    }

    /** @return list<Order> placed in the last $days days, newest first */
    public function recent(int $days): array
    {
        return array_map(self::order(...), $this->db->query(self::SELECT . ' WHERE o.created_at > now() - make_interval(days => $1) ORDER BY o.id DESC', [$days]));
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
     * Cancels pending orders older than $minutes and puts their stock back, in one statement. The UPDATE locks the
     * rows it changes, so when several workers run this at the same moment, the second one waits, then finds those
     * orders cancelled already: each order is cancelled and restocked exactly once.
     *
     * @return int how many orders were cancelled
     */
    public function expireUnconfirmed(int $minutes): int
    {
        return count($this->db->query(<<<'SQL'
            WITH expired AS (
                UPDATE orders SET status = 'cancelled', updated_at = now()
                WHERE status = 'pending' AND created_at < now() - make_interval(mins => $1)
                RETURNING id
            ), restocked AS (
                UPDATE products p SET stock = p.stock + freed.quantity
                FROM (SELECT product_id, sum(quantity) AS quantity FROM order_lines WHERE order_id IN (SELECT id FROM expired) GROUP BY product_id) AS freed
                WHERE p.id = freed.product_id
            )
            SELECT id FROM expired
            SQL, [$minutes]));
    }

    /** A compare-and-set on the status: a second click, or a second member of staff, changes nothing. */
    private function move(int $id, OrderStatus $from, OrderStatus $to): bool
    {
        return $this->db->execute(
            'UPDATE orders SET status = $3, updated_at = now() WHERE id = $1 AND status = $2',
            [$id, $from->value, $to->value],
        ) === 1;
    }

    /** @param array<string, mixed> $row */
    private static function order(array $row): Order
    {
        return new Order(...[
            ...$row,
            'status' => OrderStatus::from($row['status']),
            'lines' => array_map(fn (array $line) => new OrderLine(...$line), $row['lines']),
        ]);
    }
}

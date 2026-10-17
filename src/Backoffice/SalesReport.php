<?php

namespace Bakery\Backoffice;

use Tenma\Db\Postgres;
use Waypoint\Attributes\Inject;

/** The sales per day and product, computed in the background, and what the charts read from them. */
final class SalesReport
{
    #[Inject]
    private Postgres $db;

    /**
     * Brings daily_sales up to date with the picked-up orders, at most once per $interval seconds for the whole app.
     *
     * Every worker has the timer that calls this. The statement first claims the run: it moves the job's last_run
     * only if it is older than the interval. A second worker at the same moment waits for that row's lock, then
     * finds the run fresh, claims nothing and writes nothing. That holds across processes too. A picked-up order
     * stays picked up, so writing each day's sums over the old ones is enough.
     *
     * @return int the rows written, 0 when another worker had the run
     */
    public function aggregate(float $interval): int
    {
        return $this->db->execute(<<<'SQL'
            WITH claimed AS (
                INSERT INTO jobs (name, last_run) VALUES ('sales', now())
                ON CONFLICT (name) DO UPDATE SET last_run = now() WHERE jobs.last_run <= now() - make_interval(secs => $1)
                RETURNING name
            )
            INSERT INTO daily_sales (day, product_id, quantity, revenue_cents)
            SELECT o.pickup_date, l.product_id, sum(l.quantity), sum(l.quantity * l.price_cents)
            FROM orders o JOIN order_lines l ON l.order_id = o.id
            WHERE o.status = 'picked_up' AND EXISTS (SELECT FROM claimed)
            GROUP BY o.pickup_date, l.product_id
            ON CONFLICT (day, product_id) DO UPDATE SET quantity = excluded.quantity, revenue_cents = excluded.revenue_cents
            SQL, [$interval]);
    }

    /** @return list<array<string, mixed>> day and revenue in euros, one row per day, also for days without sales */
    public function revenueByDay(int $days, string $locale): array
    {
        return $this->db->query(<<<'SQL'
            SELECT to_char(d, $2) AS day, coalesce(sum(s.revenue_cents), 0) / 100.0 AS revenue
            FROM generate_series(current_date - ($1::int - 1), current_date, interval '1 day') AS d
            LEFT JOIN daily_sales s ON s.day = d::date
            GROUP BY d ORDER BY d
            SQL, [$days, $locale === 'de' ? 'DD.MM.' : 'DD Mon']);
    }

    /** @return list<array<string, mixed>> product name and quantity sold, best first */
    public function topProducts(int $days, string $locale): array
    {
        return $this->db->query(<<<'SQL'
            SELECT p.name ->> $2 AS product, sum(s.quantity) AS quantity
            FROM daily_sales s JOIN products p ON p.id = s.product_id
            WHERE s.day > current_date - $1::int
            GROUP BY p.id ORDER BY quantity DESC, p.position
            SQL, [$days, $locale]);
    }
}

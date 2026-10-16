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
     * Recomputes daily_sales from the picked-up orders, at most once per $interval seconds for the whole app.
     *
     * Every worker has the timer that calls this. The first statement claims the run: it updates the job's row
     * only if the last run is older than the interval. A second worker at the same moment waits for the row lock,
     * then finds the run fresh and gets nothing back, so it skips. That holds across processes too.
     */
    public function aggregate(float $interval): bool
    {
        return $this->db->transaction(function (Postgres $tx) use ($interval): bool {
            $claimed = $tx->first(
                "INSERT INTO jobs (name, last_run) VALUES ('sales', now())
                 ON CONFLICT (name) DO UPDATE SET last_run = now() WHERE jobs.last_run <= now() - make_interval(secs => $1)
                 RETURNING name",
                [$interval],
            );
            if ($claimed === null) {
                return false;
            }
            // ponytail: recomputes all days; years of a bakery's orders take milliseconds, recent days only when not
            $tx->execute('DELETE FROM daily_sales');
            $tx->execute(<<<'SQL'
                INSERT INTO daily_sales (day, product_id, quantity, revenue_cents)
                SELECT o.pickup_date, l.product_id, sum(l.quantity), sum(l.quantity * l.price_cents)
                FROM orders o JOIN order_lines l ON l.order_id = o.id
                WHERE o.status = 'picked_up'
                GROUP BY o.pickup_date, l.product_id
                SQL);
            return true;
        });
    }

    /** @return list<array{day: string, revenue: float}> one row per day, also for days without sales */
    public function revenueByDay(int $days): array
    {
        return array_map(fn (array $row) => ['day' => (string) $row['day'], 'revenue' => (float) $row['revenue']], $this->db->query(<<<'SQL'
            SELECT to_char(d, 'DD.MM.') AS day, coalesce(sum(s.revenue_cents), 0) / 100.0 AS revenue
            FROM generate_series(current_date - ($1::int - 1), current_date, interval '1 day') AS d
            LEFT JOIN daily_sales s ON s.day = d::date
            GROUP BY d ORDER BY d
            SQL, [$days]));
    }

    /** @return list<array{product: string, quantity: int}> */
    public function topProducts(int $days, string $locale): array
    {
        return array_map(fn (array $row) => ['product' => (string) $row['product'], 'quantity' => (int) $row['quantity']], $this->db->query(<<<'SQL'
            SELECT p.name ->> $2 AS product, sum(s.quantity) AS quantity
            FROM daily_sales s JOIN products p ON p.id = s.product_id
            WHERE s.day > current_date - $1::int
            GROUP BY p.id ORDER BY quantity DESC, p.position
            SQL, [$days, $locale]));
    }
}

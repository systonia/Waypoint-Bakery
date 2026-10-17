<?php

namespace Bakery\Catalog;

use Tenma\Db\Postgres;
use Waypoint\Attributes\Inject;
use Waypoint\Support\Row;

/** The range from Postgres. Every call waits only for its own query: other requests of the worker go on meanwhile. */
final class Products
{
    /** The columns Product::of() reads. */
    public const COLUMNS = 'p.id, p.slug, p.name, p.description, p.category, p.price_cents AS "priceCents", p.stock, p.picture';

    #[Inject]
    private Postgres $db;

    /** @return list<Product> */
    public function all(): array
    {
        return array_map(fn (array $row) => Product::of(new Row($row)), $this->db->query('SELECT ' . self::COLUMNS . ' FROM products p ORDER BY p.position, p.id'));
    }

    public function bySlug(string $slug): ?Product
    {
        $row = $this->db->first('SELECT ' . self::COLUMNS . ' FROM products p WHERE p.slug = $1', [$slug]);
        return $row === null ? null : Product::of(new Row($row));
    }

    public function byId(int $id): ?Product
    {
        $row = $this->db->first('SELECT ' . self::COLUMNS . ' FROM products p WHERE p.id = $1', [$id]);
        return $row === null ? null : Product::of(new Row($row));
    }

    public function setStock(int $id, int $stock): void
    {
        $this->db->execute('UPDATE products SET stock = $1 WHERE id = $2', [max(0, $stock), $id]);
    }
}

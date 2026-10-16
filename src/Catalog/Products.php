<?php

namespace Bakery\Catalog;

use Tenma\Db\Postgres;
use Waypoint\Attributes\Inject;

/** The range from Postgres. Every call waits only for its own query: other requests of the worker go on meanwhile. */
final class Products
{
    #[Inject]
    private Postgres $db;

    /** @return list<Product> */
    public function all(): array
    {
        return array_map(Product::fromRow(...), $this->db->query('SELECT * FROM products ORDER BY position, id'));
    }

    public function bySlug(string $slug): ?Product
    {
        $row = $this->db->first('SELECT * FROM products WHERE slug = $1', [$slug]);
        return $row === null ? null : Product::fromRow($row);
    }

    public function byId(int $id): ?Product
    {
        $row = $this->db->first('SELECT * FROM products WHERE id = $1', [$id]);
        return $row === null ? null : Product::fromRow($row);
    }

    public function setStock(int $id, int $stock): void
    {
        $this->db->execute('UPDATE products SET stock = $1 WHERE id = $2', [max(0, $stock), $id]);
    }
}

<?php

namespace Bakery\Repositories;

use Bakery\Models\{CartLine, Product};
use Tenma\Db\Postgres;
use Waypoint\Attributes\Inject;
use Waypoint\Support\Row;

/** The carts in Postgres, each under its browser's cart cookie. */
final class Carts
{
    #[Inject]
    private Postgres $db;

    /** @return array<int, CartLine> product id => line */
    public function lines(string $cart): array
    {
        $lines = [];
        $rows = $this->db->query(
            'SELECT c.quantity, ' . Products::COLUMNS . ' FROM cart_lines c JOIN products p ON p.id = c.product_id WHERE c.cart = $1 ORDER BY p.position',
            [$cart],
        );
        foreach ($rows as $values) {
            $row = new Row($values);
            $product = Product::of($row);
            $lines[$product->id] = new CartLine($product, $row->int('quantity'));
        }
        return $lines;
    }

    public function put(string $cart, int $product, int $quantity): void
    {
        $this->db->execute(
            'INSERT INTO cart_lines (cart, product_id, quantity) VALUES ($1, $2, $3)
             ON CONFLICT (cart, product_id) DO UPDATE SET quantity = excluded.quantity, updated_at = now()',
            [$cart, $product, $quantity],
        );
    }

    public function remove(string $cart, int $product): void
    {
        $this->db->execute('DELETE FROM cart_lines WHERE cart = $1 AND product_id = $2', [$cart, $product]);
    }

    public function clear(string $cart): void
    {
        $this->db->execute('DELETE FROM cart_lines WHERE cart = $1', [$cart]);
    }
}

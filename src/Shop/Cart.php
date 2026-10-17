<?php

namespace Bakery\Shop;

use Bakery\Catalog\{Product, Products};
use Tenma\Db\Postgres;
use Waypoint\Attributes\Inject;
use Waypoint\Live\{Connection, Scoped};

/**
 * The cart of one browser tab, shared by every component of the tab: the badge in the header, the buttons on
 * the cards, the cart page. It is kept in Postgres under the browser's cart cookie, so it is still there on the
 * next page, which is a new tab circuit.
 */
#[Scoped]
final class Cart
{
    #[Inject]
    private Connection $connection;

    #[Inject]
    private Postgres $db;

    /** @var array<int, CartLine>|null product id => line, loaded on first use */
    private ?array $lines = null;

    /** @return array<int, CartLine> product id => line */
    public function lines(): array
    {
        if ($this->lines === null) {
            $this->lines = [];
            $rows = $this->id() === null ? [] : $this->db->query(
                'SELECT c.quantity, ' . Products::COLUMNS . ' FROM cart_lines c JOIN products p ON p.id = c.product_id WHERE c.cart = $1 ORDER BY p.position',
                [$this->id()],
            );
            foreach ($rows as $row) {
                ['quantity' => $quantity] = $row;
                unset($row['quantity']);
                $product = new Product(...$row);
                $this->lines[$product->id] = new CartLine($product, $quantity);
            }
        }
        return $this->lines;
    }

    public function quantity(int $product): int
    {
        return $this->lines()[$product]->quantity ?? 0;
    }

    /** Adds one more, as long as the bakery has it. */
    public function add(Product $product): bool
    {
        return $this->set($product, $this->quantity($product->id) + 1);
    }

    /** Sets the quantity, at most what is in stock; 0 removes the line. False when the stock set the limit. */
    public function set(Product $product, int $quantity): bool
    {
        $wanted = max(0, $quantity);
        $quantity = min($wanted, $product->stock);
        $this->lines();
        $id = $this->id();
        if ($quantity === 0) {
            unset($this->lines[$product->id]);
            if ($id !== null) {
                $this->db->execute('DELETE FROM cart_lines WHERE cart = $1 AND product_id = $2', [$id, $product->id]);
            }
        } else {
            $this->lines[$product->id] = new CartLine($product, $quantity);
            if ($id !== null) {
                $this->db->execute(
                    'INSERT INTO cart_lines (cart, product_id, quantity) VALUES ($1, $2, $3)
                     ON CONFLICT (cart, product_id) DO UPDATE SET quantity = excluded.quantity, updated_at = now()',
                    [$id, $product->id, $quantity],
                );
            }
        }
        return $quantity === $wanted;
    }

    public function count(): int
    {
        return array_sum($this->quantities());
    }

    public function total(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->totalCents(), $this->lines()));
    }

    /** @return array<int, int> product id => quantity, for Orders::place() */
    public function quantities(): array
    {
        return array_map(fn (CartLine $line): int => $line->quantity, $this->lines());
    }

    public function clear(): void
    {
        $this->lines = [];
        if ($this->id() !== null) {
            $this->db->execute('DELETE FROM cart_lines WHERE cart = $1', [$this->id()]);
        }
    }

    private function id(): ?string
    {
        $id = $this->connection->request->cookies[CartCookie::NAME] ?? '';
        return CartCookie::valid($id) ? $id : null;
    }
}

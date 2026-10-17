<?php

namespace Bakery\Services;

use Bakery\Models\{CartLine, Product};
use Bakery\Repositories\Carts;
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
    private Carts $carts;

    /** @var array<int, CartLine>|null product id => line, loaded on first use */
    private ?array $lines = null;

    /** @return array<int, CartLine> product id => line */
    public function lines(): array
    {
        $id = $this->id();
        return $this->lines ??= $id === null ? [] : $this->carts->lines($id);
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
                $this->carts->remove($id, $product->id);
            }
        } else {
            $this->lines[$product->id] = new CartLine($product, $quantity);
            if ($id !== null) {
                $this->carts->put($id, $product->id, $quantity);
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
        $id = $this->id();
        if ($id !== null) {
            $this->carts->clear($id);
        }
    }

    private function id(): ?string
    {
        $id = $this->connection->request->cookies[CartCookie::NAME] ?? '';
        return CartCookie::valid($id) ? $id : null;
    }
}

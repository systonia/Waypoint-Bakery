<?php

namespace Bakery\Backoffice;

use Bakery\Catalog\{Product, Products};
use Bakery\Language;
use Waypoint\Attributes\Inject;
use Waypoint\Exceptions\ForbiddenException;
use Waypoint\Live\{Component, Connection};

/** What is left of each product. A changed number is saved as soon as the field loses the focus. */
#[Component]
final class StockEditor
{
    #[Inject]
    private Products $products;

    #[Inject]
    private Connection $connection;

    #[Inject]
    private Language $i18n;

    /** @var list<Product>|null */
    private ?array $list = null;

    private ?string $saved = null;

    public function set(Product $product, string $stock): void
    {
        if (!in_array($this->connection->request->jwt['role'] ?? null, ['owner', 'staff'], true)) {
            throw new ForbiddenException('Only for the people in the bakery');
        }
        $this->products->setStock($product->id, (int) $stock);
        $this->list = null;
        $this->saved = $this->i18n->t('Saved: {product}', ['product' => $product->name($this->i18n->locale())]);
    }

    /** @return list<Product> */
    private function list(): array
    {
        return $this->list ??= $this->products->all();
    }
}

<?php

namespace Bakery\Components;

use Bakery\Repositories\Products;
use Bakery\Services\Cart;
use Waypoint\Attributes\Inject;
use Waypoint\I18n\Translator;
use Waypoint\Live\{Component, Input};

/** The button on a product card and page. The product id comes from the page, so the action looks it up again. */
#[Component]
final class AddToCart
{
    #[Input]
    public int $product = 0;

    #[Inject]
    private Cart $cart;

    #[Inject]
    private Products $products;

    #[Inject]
    private Translator $i18n;

    private ?string $notice = null;

    public function add(): void
    {
        $product = $this->products->byId($this->product);
        if ($product === null) {
            return;
        }
        $this->notice = $this->cart->add($product) ? null : $this->i18n->t('That is all we have left.');
    }
}

<?php

namespace Bakery\Shop;

use Bakery\Language;
use Waypoint\Attributes\Inject;
use Waypoint\Live\Component;

/** The cart link in the header: it counts along whenever another component of the tab changes the cart. */
#[Component]
final class CartBadge
{
    #[Inject]
    private Cart $cart;

    #[Inject]
    private Language $i18n;
}

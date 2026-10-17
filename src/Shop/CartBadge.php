<?php

namespace Bakery\Shop;

use Waypoint\Attributes\Inject;
use Waypoint\I18n\Translator;
use Waypoint\Live\Component;

/** The cart link in the header: it counts along whenever another component of the tab changes the cart. */
#[Component]
final class CartBadge
{
    #[Inject]
    private Cart $cart;

    #[Inject]
    private Translator $i18n;
}

<?php

namespace Bakery\Tests\Services;

use Bakery\Tests\Support\AppTestCase;

final class CartTest extends AppTestCase
{
    public function testTheCartIsSharedByTheTabsComponentsAndKeptForTheNextPage(): void
    {
        $tab = $this->customerTab();
        $badge = $tab->mount('app-cart-badge');
        $tab->mount('app-add-to-cart', ['product' => '3'])->click('add')->click('add')->assertSee('2 in your cart');
        $this->assertStringContainsString('>2</span>', $badge->html());

        // The cart page is a new page, so a new tab circuit: the cart comes from Postgres
        $editor = $this->customerTab()->mount('app-cart-editor');
        $editor->assertSee('Lye pretzel')->assertSee('€2.40');
        $editor->click('more')->assertSee('€3.60');
        $editor->click('remove')->assertSee('Your cart is empty.');
        $this->assertSame(0, $this->sql('SELECT count(*) FROM cart_lines'));
    }
}

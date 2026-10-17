<?php

namespace Bakery\Tests\Components;

use Bakery\Tests\Support\AppTestCase;

final class AddToCartTest extends AppTestCase
{
    public function testTheButtonStopsAtTheStock(): void
    {
        $this->db()->execute('UPDATE products SET stock = 1 WHERE id = 3');
        $this->customerTab()->mount('app-add-to-cart', ['product' => '3'])->click('add')->click('add')
            ->assertSee('1 in your cart')
            ->assertSee('That is all we have left.');
    }
}

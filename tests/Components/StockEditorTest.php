<?php

namespace Bakery\Tests\Components;

use Bakery\Tests\Support\AppTestCase;

final class StockEditorTest extends AppTestCase
{
    public function testTheStockIsSavedFromTheField(): void
    {
        $this->staffTab(self::STAFF)->mount('app-stock-editor')
            ->change('input[aria-label="Lye pretzel"]', '55')
            ->assertSee('Saved: Lye pretzel');
        $this->assertSame(55, $this->sql('SELECT stock FROM products WHERE id = 3'));
    }
}

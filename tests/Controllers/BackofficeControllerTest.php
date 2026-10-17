<?php

namespace Bakery\Tests\Controllers;

use Bakery\Repositories\{Orders, SalesReport};
use Bakery\Tests\Support\AppTestCase;

/** The back office pages: sales and exports. */
final class BackofficeControllerTest extends AppTestCase
{
    public function testSalesAndExportsAreOnlyForTheOwner(): void
    {
        $this->request('GET', '/backoffice/sales')->withJwt(self::STAFF)->send()->assertStatus(403);
        $this->request('GET', '/backoffice/orders.csv')->withJwt(self::STAFF)->send()->assertStatus(403);
    }

    public function testTheSalesPageChartsTheSalesInTheVisitorsLanguage(): void
    {
        $this->app()->get(SalesReport::class)->aggregate(300);

        $this->request('GET', '/backoffice/sales?lang=de')->withJwt(self::OWNER)->send()
            ->assertOk()
            ->assertSee('<figure class="wp-chart wp-chart-bar"')
            ->assertSee('Umsatz pro Tag, letzte vier Wochen')
            ->assertSee(date('d.m.'))
            ->assertSee('<td>Abgeholt</td>')
            ->assertSee('<table class="wp-sheet">');
    }

    public function testTheOrdersExportAsCsvAndXlsx(): void
    {
        $this->app()->get(Orders::class)->place([2 => 2], 'Lena', 'lena@example.test', date('Y-m-d', strtotime('tomorrow')), 'de');
        $csv = $this->request('GET', '/backoffice/orders.csv')->withJwt(self::OWNER)->send()
            ->assertOk()
            ->assertContentType('text/csv')
            ->body();
        $this->assertStringContainsString('Order;Placed;Pickup;Customer;Email;Status;Items;Total (€)', $csv);
        $this->assertStringContainsString(';Lena;lena@example.test;Not confirmed yet;2 × Dinkelbrötchen (4);6,8', $csv);

        $this->request('GET', '/backoffice/orders.xlsx')->withJwt(self::OWNER)->send()
            ->assertOk()
            ->assertContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->request('GET', '/backoffice/orders.pdf')->withJwt(self::OWNER)->send()->assertStatus(404);
    }
}

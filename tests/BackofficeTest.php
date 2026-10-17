<?php

namespace Bakery\Tests;

use Bakery\Backoffice\SalesReport;
use Bakery\Ordering\Orders;
use Waypoint\Exceptions\UnauthorizedException;
use Waypoint\Http\Request;
use Waypoint\Live\Broadcast;
use Waypoint\Live\Testing\{Mounted, Tab};

/** Sign-in, the live order board, stock, sales and exports. */
final class BackofficeTest extends AppTestCase
{
    private const OWNER = ['sub' => 1, 'name' => 'Anna', 'role' => 'owner'];

    private const STAFF = ['sub' => 2, 'name' => 'Ben', 'role' => 'staff'];

    public function testSignInWithTheFormAndAFlashMessage(): void
    {
        $this->request('GET', '/backoffice')->asNavigation()->send()->assertRedirect('/backoffice/login');

        $this->request('POST', '/backoffice/login')->withBody(['email' => 'ben@sonnenkorn.test', 'password' => 'baguette'])->withCsrf()->send()
            ->assertStatus(422)
            ->assertSee('Wrong email or password.');

        $login = $this->request('POST', '/backoffice/login')->withBody(['email' => 'ben@sonnenkorn.test', 'password' => 'pretzel'])->withCsrf()->send()
            ->assertRedirect('/backoffice', 303);
        $this->request('GET', '/backoffice')
            ->withCookie('session', $login->cookie('session') ?? '')
            ->withCookie('flash', $login->cookie('flash') ?? '')
            ->send()
            ->assertOk()
            ->assertSee('Welcome, Ben.')
            ->assertSee('Sign out Ben')
            ->assertDontSee('/backoffice/sales')
            ->assertSee('<app-order-board data-live');
    }

    public function testSalesAndExportsAreForTheOwner(): void
    {
        $this->request('GET', '/backoffice/sales')->withJwt(self::STAFF)->send()->assertStatus(403);
        $this->request('GET', '/backoffice/orders.csv')->withJwt(self::STAFF)->send()->assertStatus(403);
    }

    public function testTheSalesPageChartsWhatTheJobAggregated(): void
    {
        $sales = $this->app()->get(SalesReport::class);
        $this->assertGreaterThan(0, $sales->aggregate(300));
        $this->assertSame(0, $sales->aggregate(300), 'another worker just ran it');
        $this->assertSame(
            $this->sql("SELECT sum(quantity) FROM order_lines JOIN orders ON orders.id = order_id WHERE status = 'picked_up'"),
            $this->sql('SELECT sum(quantity) FROM daily_sales'),
        );

        $this->request('GET', '/backoffice/sales?lang=de')->withJwt(self::OWNER)->send()
            ->assertOk()
            ->assertSee('<figure class="wp-chart wp-chart-bar"')
            ->assertSee('Umsatz pro Tag, letzte vier Wochen')
            ->assertSee(date('d.m.'))
            ->assertSee('<td>Abgeholt</td>')
            ->assertSee('<table class="wp-sheet">');
    }

    public function testOrdersExportAsCsvAndXlsx(): void
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

    public function testANewOrderAppearsOnEveryOpenBoard(): void
    {
        $morning = $this->board(self::STAFF);
        $office = $this->board(self::OWNER);
        $morning->assertDontSee('Lena');

        $orders = $this->app()->get(Orders::class);
        $order = $orders->place([4 => 3], 'Lena', 'lena@example.test', date('Y-m-d', strtotime('tomorrow')), 'en');
        $this->app()->get(Broadcast::class)->changed();   // what the checkout does
        \Tenma::sleep(0.05);
        $morning->assertSee('Lena')->assertSee('3 × Butter croissant')->assertSee('Not confirmed yet');
        $office->assertSee('Lena');

        // A confirmed order can be moved on from either board, and both follow
        $orders->confirm($order->id);
        $this->app()->get(Broadcast::class)->changed();
        \Tenma::sleep(0.05);
        $morning->click('li[data-key="' . $order->code . '"] button');
        \Tenma::sleep(0.05);
        $this->assertSame('baking', $this->sql('SELECT status FROM orders WHERE id = $1', [$order->id]));
        $this->assertMatchesRegularExpression('/data-key="baking".*data-key="' . $order->code . '"/s', $office->html());
    }

    public function testTheBoardIsOnlyForSignedInStaff(): void
    {
        $this->expectException(UnauthorizedException::class);
        Tab::for($this->app())->mount('app-order-board');
    }

    public function testStockIsSavedFromTheField(): void
    {
        $this->tab(self::STAFF)->mount('app-stock-editor')
            ->change('input[aria-label="Lye pretzel"]', '55')
            ->assertSee('Saved: Lye pretzel');
        $this->assertSame(55, $this->sql('SELECT stock FROM products WHERE id = 3'));
    }

    /** @param array<string, mixed> $user */
    private function board(array $user): Mounted
    {
        return $this->tab($user)->mount('app-order-board');
    }

    /** @param array<string, mixed> $user */
    private function tab(array $user): Tab
    {
        $request = Request::fromParts('GET', '/_live');
        $request->jwt = $user;
        return Tab::for($this->app(), $request);
    }
}

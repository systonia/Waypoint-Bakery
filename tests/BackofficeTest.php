<?php

namespace Bakery\Tests;

use Bakery\Backoffice\SalesReport;
use Bakery\Ordering\{OrderFeed, Orders};
use DateTimeImmutable;
use Tenma\Subscription;
use Waypoint\Http\Request;
use Waypoint\Live\Testing\Tab;

/** Sign-in, the live order board, stock, sales and exports. */
final class BackofficeTest extends AppTestCase
{
    private ?Subscription $feed = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feed = $this->service(OrderFeed::class)->listen();
    }

    protected function tearDown(): void
    {
        \Tenma::sleep(0.02);
        $this->feed?->cancel();
        parent::tearDown();
    }

    public function testSignInWithTheFormAndAFlashMessage(): void
    {
        $this->get('/backoffice')->assertStatus(401);
        $this->get('/backoffice', ['Sec-Fetch-Mode' => 'navigate'])->assertRedirect('/backoffice/login');

        $this->request('POST', '/backoffice/login')->withBody(['email' => 'ben@sonnenkorn.test', 'password' => 'baguette'])->withCsrf()->send()
            ->assertStatus(422)
            ->assertSee('Wrong email or password.');

        $login = $this->request('POST', '/backoffice/login')->withBody(['email' => 'ben@sonnenkorn.test', 'password' => 'pretzel'])->withCsrf()->send()
            ->assertRedirect('/backoffice', 303);
        $this->request('GET', '/backoffice')
            ->withCookie('session', (string) $login->cookie('session'))
            ->withCookie('flash', (string) $login->cookie('flash'))
            ->send()
            ->assertOk()
            ->assertSee('Welcome, Ben.')
            ->assertSee('<app-order-board data-live');
    }

    public function testSalesAndExportsAreForTheOwner(): void
    {
        $this->request('GET', '/backoffice/sales')->withCookie('session', $this->session('staff'))->send()->assertStatus(403);
        $this->request('GET', '/backoffice/orders.csv')->withCookie('session', $this->session('staff'))->send()->assertStatus(403);
    }

    public function testTheSalesPageChartsWhatTheJobAggregated(): void
    {
        $this->assertTrue($this->service(SalesReport::class)->aggregate(300));
        $this->assertFalse($this->service(SalesReport::class)->aggregate(300), 'another worker just ran it');
        $this->assertSame(
            $this->sql("SELECT sum(quantity) FROM order_lines JOIN orders ON orders.id = order_id WHERE status = 'picked_up'"),
            $this->sql('SELECT sum(quantity) FROM daily_sales'),
        );

        $this->request('GET', '/backoffice/sales')->withCookie('session', $this->session('owner'))->send()
            ->assertOk()
            ->assertSee('<figure class="wp-chart wp-chart-bar"')
            ->assertSee('Revenue per day, last four weeks')
            ->assertSee('<table class="wp-sheet">');
    }

    public function testOrdersExportAsCsvAndXlsx(): void
    {
        $this->service(Orders::class)->place([2 => 2], 'Lena', 'lena@example.test', new DateTimeImmutable('tomorrow')->format('Y-m-d'), 'de');
        $csv = $this->request('GET', '/backoffice/orders.csv')->withCookie('session', $this->session('owner'))->send()
            ->assertOk()
            ->assertContentType('text/csv')
            ->body();
        $this->assertStringContainsString('Order;Placed;Pickup;Customer;Email;Status;Items;Total (€)', $csv);
        $this->assertStringContainsString(';Lena;lena@example.test;pending;2 × Dinkelbrötchen (4);6,8', $csv);

        $this->request('GET', '/backoffice/orders.xlsx')->withCookie('session', $this->session('owner'))->send()
            ->assertOk()
            ->assertContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->request('GET', '/backoffice/orders.pdf')->withCookie('session', $this->session('owner'))->send()->assertStatus(404);
    }

    public function testANewOrderAppearsOnEveryOpenBoard(): void
    {
        $morning = $this->board('staff');
        $office = $this->board('owner');
        $morning->assertDontSee('Lena');

        $order = $this->service(Orders::class)->place([4 => 3], 'Lena', 'lena@example.test', new DateTimeImmutable('tomorrow')->format('Y-m-d'), 'en');
        $this->service(OrderFeed::class)->announce($order->id);   // what the checkout does
        \Tenma::sleep(0.05);
        $morning->assertSee('Lena')->assertSee('3 × Butter croissant');
        $office->assertSee('Lena');

        // A confirmed order can be moved on from either board, and both follow
        $this->service(Orders::class)->confirm($order->id);
        $this->service(OrderFeed::class)->announce($order->id);
        \Tenma::sleep(0.05);
        $morning->click('li[data-key="' . $order->code . '"] button');
        \Tenma::sleep(0.05);
        $this->assertSame('baking', $this->sql('SELECT status FROM orders WHERE id = $1', [$order->id]));
        $this->assertMatchesRegularExpression('/data-key="baking".*data-key="' . $order->code . '"/s', $office->html());
    }

    public function testTheBoardShowsNothingWithoutSignIn(): void
    {
        Tab::for($this->app())->mount('app-order-board')->assertSee('Please sign in.')->assertDontSee('Mia');
    }

    public function testStockIsSavedFromTheField(): void
    {
        $this->tab('staff')->mount('app-stock-editor')
            ->change('input[aria-label="Lye pretzel"]', '55')
            ->assertSee('Saved: Lye pretzel');
        $this->assertSame(55, $this->sql('SELECT stock FROM products WHERE id = 3'));
    }

    private function board(string $role): \Waypoint\Live\Testing\Mounted
    {
        return $this->tab($role)->mount('app-order-board');
    }

    private function tab(string $role): Tab
    {
        $request = Request::fromParts('GET', '/_live');
        $request->jwt = ['sub' => $role === 'owner' ? 1 : 2, 'name' => 'Test', 'role' => $role];
        return Tab::for($this->app(), $request);
    }

    private function session(string $role): string
    {
        return \Waypoint\JWT::encode(['sub' => $role === 'owner' ? 1 : 2, 'name' => 'Test', 'role' => $role]);
    }
}

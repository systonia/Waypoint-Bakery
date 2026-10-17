<?php

namespace Bakery\Tests;

use Bakery\Ordering\{OrderStatus, Orders, OutOfStock};
use Bakery\Pickup\PickupCalendar;
use Waypoint\Http\Request;
use Waypoint\I18n\Translator;
use Waypoint\Live\Testing\Tab;

/** The customer's way through the shop: the cart across pages, the checkout, the order page that follows the bakery. */
final class OrderingTest extends AppTestCase
{
    private const CART = '0b3c2a1e-5d4f-4a8b-9c7d-6e5f4a3b2c1d';

    public function testTheCartIsSharedByTheTabsComponentsAndKeptForTheNextPage(): void
    {
        $tab = $this->tab();
        $badge = $tab->mount('app-cart-badge');
        $tab->mount('app-add-to-cart', ['product' => '3'])->click('add')->click('add')->assertSee('2 in your cart');
        $this->assertStringContainsString('>2</span>', $badge->html());

        // The cart page is a new page, so a new tab circuit: the cart comes from Postgres
        $editor = $this->tab()->mount('app-cart-editor');
        $editor->assertSee('Lye pretzel')->assertSee('€2.40');
        $editor->click('more')->assertSee('€3.60');
        $editor->click('remove')->assertSee('Your cart is empty.');
        $this->assertSame(0, $this->sql('SELECT count(*) FROM cart_lines'));
    }

    public function testTheCartStopsAtTheStock(): void
    {
        $this->db()->execute('UPDATE products SET stock = 1 WHERE id = 3');
        $this->tab()->mount('app-add-to-cart', ['product' => '3'])->click('add')->click('add')
            ->assertSee('1 in your cart')
            ->assertSee('That is all we have left.');
    }

    public function testTheCheckoutValidatesInTheCustomersLanguage(): void
    {
        $this->app()->get(Translator::class)->setLocale('de');
        $tab = $this->tab();
        $tab->mount('app-add-to-cart', ['product' => '4'])->click('add');
        $tab->mount('app-checkout')
            ->submit('form', ['name' => '', 'email' => 'nope', 'pickupDate' => date('Y-m-d')])
            ->assertSee('Dieser Wert darf nicht leer sein.')
            ->assertSee('Dieser Wert ist keine gültige E-Mail-Adresse.')
            ->assertSee('Bitte wähle einen der Abholtage.');
        $this->assertSame(0, $this->sql("SELECT count(*) FROM orders WHERE status = 'pending'"));
    }

    public function testPlacingAnOrderReservesTheStockAndLeadsToTheOrderPage(): void
    {
        $tab = $this->tab();
        $tab->mount('app-add-to-cart', ['product' => '4'])->click('add')->click('add');
        $tab->mount('app-checkout')->submit('form', ['name' => 'Mia', 'email' => 'mia@example.test', 'pickupDate' => $this->day()]);

        $code = $this->sql("SELECT code FROM orders WHERE email = 'mia@example.test'");
        $this->assertIsString($code);
        $tab->assertNavigatedTo("/orders/$code/thanks");
        $this->assertSame(34, $this->sql('SELECT stock FROM products WHERE id = 4'));
        $this->assertSame(0, $this->sql('SELECT count(*) FROM cart_lines'));

        // The thanks route leaves a flash message for the order page
        $thanks = $this->get("/orders/$code/thanks")->assertRedirect("/orders/$code", 303);
        $this->request('GET', "/orders/$code")->withCookie('flash', $thanks->cookie('flash') ?? '')->send()
            ->assertOk()
            ->assertSee('Thank you, Mia! Your order is reserved.')
            ->assertSee('<app-order-tracker data-live');
    }

    public function testTheLastPieceGoesToOnlyOneCustomer(): void
    {
        $this->db()->execute('UPDATE products SET stock = 1 WHERE id = 6');
        $orders = $this->app()->get(Orders::class);
        $this->assertSame(OrderStatus::Pending, $orders->place([6 => 1], 'Mia', 'mia@example.test', $this->day(), 'en')->status);
        $this->expectException(OutOfStock::class);
        $orders->place([6 => 1, 2 => 1], 'Noah', 'noah@example.test', $this->day(), 'en');
    }

    public function testTheOrderPageFollowsTheBakery(): void
    {
        $orders = $this->app()->get(Orders::class);
        $order = $orders->place([1 => 1], 'Mia', 'mia@example.test', $this->day(), 'en');
        $tracker = $this->tab()->mount('app-order-tracker', ['code' => $order->code])->assertSee('Please confirm the order');

        $tracker->click('confirm')->assertSee('Received')->assertDontSee('Please confirm');

        // The bakery moves it on somewhere else: here, the API
        $orders->advance($order->id, OrderStatus::Received);
        $this->broadcast();
        $this->assertMatchesRegularExpression('/aria-current="step"\s*>\s*In the oven/', $tracker->html());
    }

    public function testUnconfirmedOrdersExpireAndGiveTheirStockBack(): void
    {
        $orders = $this->app()->get(Orders::class);
        $order = $orders->place([5 => 3], 'Noah', 'noah@example.test', $this->day(), 'en');
        $this->assertSame(21, $this->sql('SELECT stock FROM products WHERE id = 5'));

        $this->assertSame(0, $orders->expireUnconfirmed(15), 'too early');
        $this->db()->execute("UPDATE orders SET created_at = now() - interval '16 minutes' WHERE id = $1", [$order->id]);
        $this->assertSame(1, $orders->expireUnconfirmed(15));
        $this->assertSame(0, $orders->expireUnconfirmed(15), 'only once');
        $this->assertSame(24, $this->sql('SELECT stock FROM products WHERE id = 5'));
        $this->assertSame('cancelled', $this->sql('SELECT status FROM orders WHERE id = $1', [$order->id]));
    }

    private function tab(): Tab
    {
        return Tab::for($this->app(), Request::fromParts('GET', '/_live', cookies: ['cart' => self::CART]));
    }

    /** The first pickup day. */
    private function day(): string
    {
        return $this->app()->get(PickupCalendar::class)->upcoming()[0];
    }
}

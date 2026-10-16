<?php

namespace Bakery\Shop;

use Bakery\{Format, Language};
use Bakery\Ordering\{OrderFeed, Orders, OutOfStock};
use Bakery\Pickup\{Holidays, OpeningHours, PickupCalendar};
use DateTimeImmutable;
use Waypoint\Attributes\Inject;
use Waypoint\Live\{Component, Connection, Input};

/**
 * The checkout form. Placing an order reserves the stock, empties the tab's cart, tells every worker about the
 * new order (the order boards in the back office show it at once) and sends the browser to the order's page.
 */
#[Component]
final class Checkout
{
    /** @var list<string> the pickup days the cart page found; checked again when the order is placed */
    #[Input]
    public array $days = [];

    #[Inject]
    private Cart $cart;

    #[Inject]
    private Orders $orders;

    #[Inject]
    private OrderFeed $feed;

    #[Inject]
    private OpeningHours $hours;

    #[Inject]
    private Holidays $holidays;

    #[Inject]
    private Connection $connection;

    #[Inject]
    private Language $i18n;

    private CheckoutForm $form;

    /** @var array<string, string> field => message */
    private array $errors = [];

    public function __construct()
    {
        $this->form = new CheckoutForm();
    }

    /** @param array<string, mixed> $fields */
    public function place(array $fields): void
    {
        $this->form = new CheckoutForm($fields);
        $this->errors = $this->form->errors;
        if ($this->cart->count() === 0) {
            $this->errors['cart'] = $this->i18n->t('Your cart is empty.');
        }
        if (!isset($this->errors['pickupDate']) && !in_array($this->form->pickupDate, $this->pickupDays(), true)) {
            $this->errors['pickupDate'] = $this->i18n->t('Please choose one of the pickup days.');
        }
        if ($this->errors !== []) {
            return;
        }
        try {
            $order = $this->orders->place($this->cart->quantities(), trim($this->form->name), trim($this->form->email), $this->form->pickupDate, $this->i18n->locale());
        } catch (OutOfStock $e) {
            $this->errors['cart'] = $this->i18n->t('Sorry, we do not have that many of {product} any more.', ['product' => $e->product]);
            return;
        }
        $this->cart->clear();
        $this->feed->announce($order->id);
        $this->connection->navigate("/orders/{$order->code}/thanks");
    }

    /** @return list<string> */
    private function pickupDays(): array
    {
        ['hours' => $hours, 'holidays' => $holidays] = \Tenma::all(
            hours: fn () => $this->hours->week(),
            holidays: fn () => $this->holidays->upcoming(),
        );
        return PickupCalendar::days(new DateTimeImmutable('today'), $hours, $holidays ?? []);
    }

    private function day(string $date): string
    {
        return Format::day($date, $this->i18n->locale());
    }
}

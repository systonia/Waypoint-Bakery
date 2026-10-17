<?php

namespace Bakery\Components;

use Bakery\Models\OutOfStock;
use Bakery\Repositories\Orders;
use Bakery\Requests\CheckoutForm;
use Bakery\Services\{Cart, Format, PickupCalendar};
use Waypoint\Attributes\Inject;
use Waypoint\I18n\Translator;
use Waypoint\Live\{Broadcast, Component, Connection};

/**
 * The checkout form. Placing an order reserves the stock, empties the tab's cart, renders every open tab (the
 * order boards in the back office show the new order at once) and sends the browser to the order's page.
 */
#[Component]
final class Checkout
{
    #[Inject]
    private Cart $cart;

    #[Inject]
    private Orders $orders;

    #[Inject]
    private PickupCalendar $calendar;

    #[Inject]
    private Broadcast $broadcast;

    #[Inject]
    private Connection $connection;

    #[Inject]
    private Translator $i18n;

    private CheckoutForm $form;

    /** @var array<string, string> field => message */
    private array $errors = [];

    public function __construct()
    {
        $this->form = new CheckoutForm();
    }

    public function place(CheckoutForm $form): void
    {
        $this->form = $form;
        $this->errors = $form->errors;
        if ($this->cart->count() === 0) {
            $this->errors['cart'] = $this->i18n->t('Your cart is empty.');
        }
        if (!isset($this->errors['pickupDate']) && !in_array($form->pickupDate, $this->calendar->upcoming(), true)) {
            $this->errors['pickupDate'] = $this->i18n->t('Please choose one of the pickup days.');
        }
        if ($this->errors !== []) {
            return;
        }
        try {
            $order = $this->orders->place($this->cart->quantities(), trim($form->name), trim($form->email), $form->pickupDate, $this->i18n->locale());
        } catch (OutOfStock $e) {
            $this->errors['cart'] = $this->i18n->t('Sorry, we do not have that many of {product} any more.', ['product' => $e->product]);
            return;
        }
        $this->cart->clear();
        $this->broadcast->changed();
        $this->connection->navigate("/orders/{$order->code}/thanks");
    }
}

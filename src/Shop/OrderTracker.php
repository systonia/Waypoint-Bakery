<?php

namespace Bakery\Shop;

use Bakery\{Format, Language};
use Bakery\Ordering\{Order, OrderFeed, OrderStatus, Orders};
use DateTimeImmutable;
use Waypoint\Attributes\Inject;
use Waypoint\Environment;
use Waypoint\Live\{Component, Input};

/**
 * The customer's order page. It changes when the bakery moves the order on, in whichever worker that happened:
 * the order feed reloads the order and renders this tab. The code in the link is the customer's key.
 */
#[Component]
final class OrderTracker
{
    #[Input]
    public string $code = '';

    #[Inject]
    private OrderFeed $feed;

    #[Inject]
    private Orders $orders;

    #[Inject]
    private Environment $env;

    #[Inject]
    private Language $i18n;

    public function confirm(): void
    {
        $order = $this->order();
        if ($order !== null && $this->orders->confirm($order->id)) {
            $this->feed->announce($order->id);
        }
    }

    private function order(): ?Order
    {
        return $this->feed->get($this->code);
    }

    /** @return list<OrderStatus> */
    private function steps(): array
    {
        return [OrderStatus::Received, OrderStatus::Baking, OrderStatus::Ready, OrderStatus::PickedUp];
    }

    private function reservedUntil(Order $order): string
    {
        return new DateTimeImmutable($order->createdAt)
            ->modify('+' . (int) $this->env->get('UNCONFIRMED_MINUTES', 15) . ' minutes')
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('H:i');
    }

    private function price(int $cents): string
    {
        return Format::price($cents, $this->i18n->locale());
    }
}

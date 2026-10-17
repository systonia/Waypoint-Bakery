<?php

namespace Bakery\Components;

use Bakery\Models\{Order, OrderStatus};
use Bakery\Repositories\Orders;
use Bakery\Services\Format;
use DateTimeImmutable;
use Waypoint\Attributes\Inject;
use Waypoint\Environment;
use Waypoint\I18n\Translator;
use Waypoint\Live\{Broadcast, Component, Input};

/**
 * The customer's order page. It changes when the bakery moves the order on, in whichever worker that happened:
 * the broadcast renders this tab, which reads the order again. The code in the link is the customer's key.
 */
#[Component]
final class OrderTracker
{
    #[Input]
    public string $code = '';

    #[Inject]
    private Orders $orders;

    #[Inject]
    private Broadcast $broadcast;

    #[Inject]
    private Environment $env;

    #[Inject]
    private Translator $i18n;

    public function confirm(): void
    {
        $order = $this->order();
        if ($order !== null && $this->orders->confirm($order->id)) {
            $this->broadcast->changed();
        }
    }

    private function order(): ?Order
    {
        return $this->orders->byCode($this->code);
    }

    /** @return list<OrderStatus> */
    private function steps(): array
    {
        return [OrderStatus::Received, OrderStatus::Baking, OrderStatus::Ready, OrderStatus::PickedUp];
    }

    private function reservedUntil(Order $order): string
    {
        return new DateTimeImmutable($order->createdAt)
            ->modify('+' . $this->env->int('UNCONFIRMED_MINUTES', 15) . ' minutes')
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('H:i');
    }
}

<?php

namespace Bakery\Backoffice;

use Bakery\{Format, Language};
use Bakery\Ordering\{Order, OrderFeed, OrderStatus, Orders};
use Waypoint\Attributes\Inject;
use Waypoint\Exceptions\ForbiddenException;
use Waypoint\Live\{Component, Connection};

/**
 * Every open order in columns by status. A new order from the shop, or a step another member of staff took in
 * another tab or on another worker, shows up here on its own: the order feed renders every tab when an order changes.
 */
#[Component]
final class OrderBoard
{
    #[Inject]
    private OrderFeed $feed;

    #[Inject]
    private Orders $orders;

    #[Inject]
    private Connection $connection;

    #[Inject]
    private Language $i18n;

    /** Bound to the order the board showed: the click moves it from the status it had then, or does nothing. */
    public function advance(Order $order): void
    {
        $this->staffOnly();
        if ($this->orders->advance($order->id, $order->status)) {
            $this->feed->announce($order->id);
        }
    }

    /** A live component checks the user itself: /_live is one route for every component. */
    private function isStaff(): bool
    {
        return in_array($this->connection->request->jwt['role'] ?? null, ['owner', 'staff'], true);
    }

    private function staffOnly(): void
    {
        if (!$this->isStaff()) {
            throw new ForbiddenException('Only for the people in the bakery');
        }
    }

    /** @return array<string, list<Order>> status => orders, in the order of the steps */
    private function columns(): array
    {
        $columns = [];
        foreach ([OrderStatus::Pending, OrderStatus::Received, OrderStatus::Baking, OrderStatus::Ready] as $status) {
            $columns[$status->value] = [];
        }
        foreach ($this->feed->open() as $order) {
            $columns[$order->status->value][] = $order;
        }
        return $columns;
    }

    private function day(string $date): string
    {
        return Format::day($date, $this->i18n->locale());
    }

    private function price(int $cents): string
    {
        return Format::price($cents, $this->i18n->locale());
    }
}

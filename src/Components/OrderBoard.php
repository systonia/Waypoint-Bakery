<?php

namespace Bakery\Components;

use Bakery\Models\{Order, OrderStatus};
use Bakery\Repositories\Orders;
use Bakery\Services\Format;
use Waypoint\Attributes\{Authenticated, Inject};
use Waypoint\I18n\Translator;
use Waypoint\Live\{Broadcast, Component};

/**
 * Every open order in columns by status, for everyone signed in to the back office. A new order from the shop, or
 * a step another member of staff took in another tab or on another worker, shows up here on its own: whoever
 * changes an order calls Broadcast::changed(), which renders every tab of every worker.
 */
#[Component]
#[Authenticated]
final class OrderBoard
{
    #[Inject]
    private Orders $orders;

    #[Inject]
    private Broadcast $broadcast;

    #[Inject]
    private Translator $i18n;

    /** Bound to the order the board showed: the click moves it from the status it had then, or does nothing. */
    public function advance(Order $order): void
    {
        if ($this->orders->advance($order->id, $order->status)) {
            $this->broadcast->changed();
        }
    }

    /** @return list<array{OrderStatus, list<Order>}> each step with its orders */
    private function columns(): array
    {
        $byStatus = [];
        foreach ($this->orders->open() as $order) {
            $byStatus[$order->status->value][] = $order;
        }
        return array_map(fn (OrderStatus $status) => [$status, $byStatus[$status->value] ?? []], OrderStatus::BOARD);
    }
}

<?php

namespace Bakery\Ordering;

use Tenma\Subscription;
use Waypoint\Attributes\Inject;
use Waypoint\Live\Broadcast;

/**
 * The orders that open tabs show, kept in this worker's memory and in step with every other worker.
 *
 * Whoever changes an order (a checkout, the order board, the API, the expiry job) calls announce() with its id.
 * Tenma::publish() hands the id to every worker of the process (and with `tenma --pubsub redis://...` to every
 * process), the subscription from listen() reloads that one order from Postgres and renders this worker's tabs.
 * Postgres stays the only truth; the message only says which order to read again.
 */
final class OrderFeed
{
    private const CHANNEL = 'orders';

    #[Inject]
    private Orders $orders;

    #[Inject]
    private Broadcast $broadcast;

    // ponytail: every order a tab looked at since the worker started; a bakery's few hundred a day fit, prune when they don't
    /** @var array<string, Order> code => order */
    private array $known = [];

    private bool $loaded = false;

    /** Tells every worker that this order changed. */
    public function announce(int $id): void
    {
        \Tenma::publish(self::CHANNEL, (string) $id);
    }

    /** Once per worker, at startup: keep this worker's copy current and its tabs rendered. */
    public function listen(): Subscription
    {
        return \Tenma::subscribe(self::CHANNEL, function (string $id): void {
            $order = $this->orders->byId((int) $id);
            if ($order !== null) {
                $this->known[$order->code] = $order;
            }
            $this->broadcast->changedHere();
        });
    }

    /** @return list<Order> the orders the board shows, read from Postgres the first time */
    public function open(): array
    {
        if (!$this->loaded) {
            foreach ($this->orders->open() as $order) {
                $this->known[$order->code] ??= $order;
            }
            $this->loaded = true;
        }
        $open = array_values(array_filter($this->known, fn (Order $order): bool => $order->status->isOpen()));
        usort($open, fn (Order $a, Order $b): int => [$a->pickupDate, $a->id] <=> [$b->pickupDate, $b->id]);
        return $open;
    }

    public function get(string $code): ?Order
    {
        if (!isset($this->known[$code]) && ($order = $this->orders->byCode($code)) !== null) {
            $this->known[$code] = $order;
        }
        return $this->known[$code] ?? null;
    }
}

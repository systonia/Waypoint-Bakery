<?php

namespace Bakery\Ordering;

use Bakery\Language;

/** pending (reserved, waiting for the customer) → received → baking → ready → picked up; pending → cancelled */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Received = 'received';
    case Baking = 'baking';
    case Ready = 'ready';
    case PickedUp = 'picked_up';
    case Cancelled = 'cancelled';

    /** The step the bakery takes next, null for an order that is done or still waits for its customer. */
    public function next(): ?self
    {
        return match ($this) {
            self::Received => self::Baking,
            self::Baking => self::Ready,
            self::Ready => self::PickedUp,
            default => null,
        };
    }

    /** Reserved, waiting for the customer to confirm it. */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isOpen(): bool
    {
        return $this !== self::PickedUp && $this !== self::Cancelled;
    }

    public function label(Language $i18n): string
    {
        return match ($this) {
            self::Pending => $i18n->t('Waiting for your confirmation'),
            self::Received => $i18n->t('Received'),
            self::Baking => $i18n->t('In the oven'),
            self::Ready => $i18n->t('Ready for pickup'),
            self::PickedUp => $i18n->t('Picked up'),
            self::Cancelled => $i18n->t('Cancelled'),
        };
    }
}

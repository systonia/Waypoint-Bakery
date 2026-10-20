<?php

namespace Bakery\Controllers;

use Bakery\Models\{Order, OrderLine, SalesPage};
use Bakery\Repositories\{Orders, SalesReport};
use DateTimeImmutable;
use Waypoint\Attributes\{Authenticated, Controller, Get, Ignore, Inject, Layout, Role};
use Waypoint\Charts\Chart;
use Waypoint\Exceptions\NotFoundException;
use Waypoint\Export\Sheet;
use Waypoint\Http\View;
use Waypoint\I18n\Translator;

/**
 * The back office's pages. Sales and exports are for the owner; the order board and the stock for everyone in the
 * bakery. #[Ignore] keeps the exports, which are no View, out of the API's OpenAPI document.
 */
#[Controller('/backoffice')]
#[Ignore]
#[Authenticated]
#[Layout('_Backoffice.php')]
final class BackofficeController
{
    #[Inject]
    private SalesReport $sales;

    #[Inject]
    private Orders $orders;

    #[Inject]
    private Translator $translator;

    #[Get('')]
    public function board(): View
    {
        return new View('Backoffice/Board');
    }

    #[Get('/stock')]
    public function stock(): View
    {
        return new View('Backoffice/Stock');
    }

    #[Get('/sales')]
    #[Role('owner')]
    public function sales(): View
    {
        $locale = $this->translator->locale();
        // The three queries run at once; each closure fills its variable
        $revenue = $products = $orders = [];
        \Tenma::all(
            function () use (&$revenue, $locale): void { $revenue = $this->sales->revenueByDay(28, $locale); },
            function () use (&$products, $locale): void { $products = $this->sales->topProducts(28, $locale); },
            function () use (&$orders): void { $orders = $this->orders->recent(7); },
        );
        return new View('Backoffice/Sales', new SalesPage(
            revenue: Chart::fromRows($revenue, x: 'day', y: 'revenue')
                ->title($this->translator->t('Revenue per day, last four weeks'))
                ->unit('€'),
            products: Chart::fromRows($products, x: 'product', y: 'quantity')
                ->title($this->translator->t('Sold in the last four weeks'))
                ->size(640, 280),
            latest: $this->sheet('orders.html', $orders),
        ));
    }

    /** The last 90 days of orders as orders.csv or orders.xlsx. */
    #[Get('/orders.{format}')]
    #[Role('owner')]
    public function export(string $format): Sheet
    {
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new NotFoundException("No export as $format");
        }
        return $this->sheet("orders.$format", $this->orders->recent(90));
    }

    /** @param list<Order> $orders */
    private function sheet(string $file, array $orders): Sheet
    {
        $placed = fn (Order $o) => new DateTimeImmutable($o->createdAt);
        $pickup = fn (Order $o) => new DateTimeImmutable($o->pickupDate);
        $customer = fn (Order $o) => $o->customerName;
        $status = fn (Order $o) => $o->status->label($this->translator);
        $items = fn (Order $o) => implode(', ', array_map(fn (OrderLine $l) => "$l->quantity × $l->name", $o->lines));
        $total = fn (Order $o) => $o->totalCents() / 100;
        return Sheet::file($file)
            ->title($this->translator->t('Orders'))
            ->column('code', $this->translator->t('Order'))
            ->column('created', $this->translator->t('Placed'), $placed)
            ->column('pickup', $this->translator->t('Pickup'), $pickup)
            ->column('customer', $this->translator->t('Customer'), $customer)
            ->column('email', $this->translator->t('Email'))
            ->column('status', $this->translator->t('Status'), $status)
            ->column('items', $this->translator->t('Items'), $items)
            ->column('total', $this->translator->t('Total (€)'), $total)
            ->rows($orders);
    }
}

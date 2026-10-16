<?php

namespace Bakery\Backoffice;

use Bakery\Ordering\{Order, Orders};
use DateTimeImmutable;
use Waypoint\Attributes\{Authenticated, Controller, Get, Inject, Role};
use Waypoint\Charts\Chart;
use Waypoint\Exceptions\NotFoundException;
use Waypoint\Export\Sheet;
use Waypoint\Http\{Request, View};
use Waypoint\I18n\Translator;

/** The back office's pages. Sales and exports are for the owner; the order board and the stock for everyone in the bakery. */
#[Controller('/backoffice')]
#[Authenticated]
final class BackofficeController
{
    #[Inject]
    private SalesReport $sales;

    #[Inject]
    private Orders $orders;

    #[Inject]
    private Translator $translator;

    #[Get('')]
    public function board(Request $req): View
    {
        return new View('Backoffice/Board', ['user' => $req->jwt], layout: '_Backoffice.php');
    }

    #[Get('/stock')]
    public function stock(Request $req): View
    {
        return new View('Backoffice/Stock', ['user' => $req->jwt], layout: '_Backoffice.php');
    }

    #[Get('/sales')]
    #[Role('owner')]
    public function sales(Request $req): View
    {
        $locale = $this->translator->locale();
        ['revenue' => $revenue, 'products' => $products, 'orders' => $orders] = \Tenma::all(
            revenue: fn () => $this->sales->revenueByDay(28),
            products: fn () => $this->sales->topProducts(28, $locale),
            orders: fn () => $this->orders->recent(7),
        );
        return new View('Backoffice/Sales', [
            'revenue' => Chart::fromRows($revenue, x: 'day', y: 'revenue')
                ->title($this->translator->t('Revenue per day, last four weeks'))
                ->unit('€'),
            'products' => Chart::fromRows($products, x: 'product', y: 'quantity')
                ->title($this->translator->t('Sold in the last four weeks'))
                ->size(640, 280),
            'latest' => $this->sheet('orders.html', $orders),
            'user' => $req->jwt,
        ], layout: '_Backoffice.php');
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
        return Sheet::file($file)
            ->title($this->translator->t('Orders'))
            ->column('code', $this->translator->t('Order'))
            ->column('created', $this->translator->t('Placed'), fn (Order $o) => new DateTimeImmutable($o->createdAt))
            ->column('pickup', $this->translator->t('Pickup'), fn (Order $o) => new DateTimeImmutable($o->pickupDate))
            ->column('customer', $this->translator->t('Customer'), fn (Order $o) => $o->customerName)
            ->column('email', $this->translator->t('Email'))
            ->column('status', $this->translator->t('Status'), fn (Order $o) => $o->status->value)
            ->column('items', $this->translator->t('Items'), fn (Order $o) => implode(', ', array_map(fn (array $l) => "{$l['quantity']} × {$l['name']}", $o->lines)))
            ->column('total', $this->translator->t('Total (€)'), fn (Order $o) => $o->totalCents / 100)
            ->rows($orders);
    }
}

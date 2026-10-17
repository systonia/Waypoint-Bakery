<?php

namespace Bakery\Shop;

use Bakery\Catalog\Products;
use Bakery\Ordering\Orders;
use Bakery\Pickup\{Holidays, LastKnown, OpeningHours, PickupCalendar, Weather};
use DateTimeImmutable;
use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Exceptions\NotFoundException;
use Waypoint\Flash\Flash;
use Waypoint\Http\{Redirect, Request, Response, View};
use Waypoint\I18n\Translator;

/** The storefront's pages. They are plain Waypoint views; the parts that react to clicks are live components in them. */
#[Controller]
final class ShopController
{
    #[Inject]
    private Products $products;

    #[Inject]
    private OpeningHours $hours;

    #[Inject]
    private Holidays $holidays;

    #[Inject]
    private Weather $weather;

    #[Inject]
    private LastKnown $lastKnown;

    #[Inject]
    private Orders $orders;

    #[Inject]
    private Flash $flash;

    #[Inject]
    private Translator $translator;

    #[Get('/')]
    public function catalog(): View
    {
        return new View('Shop/Catalog', $this->products->all());
    }

    /**
     * The product with its next pickup days, each with the weather. The holidays and the weather come from this
     * worker's memory (LastKnown, refreshed by timers in index.php), so the page only waits for Postgres. With
     * ?live they are fetched on this request instead, together with the rest by Tenma::all(): the page then takes
     * as long as the slowest source. Server-Timing says which version ran and how long it took.
     */
    #[Get('/products/{slug}')]
    public function product(string $slug, Request $req, Response $res): View
    {
        $started = hrtime(true);
        $live = $req->query('live') !== null;
        // The sources are asked at once; each closure fills its variable
        $product = $holidays = $weather = null;
        $hours = [];
        \Tenma::all(
            function () use (&$product, $slug): void { $product = $this->products->bySlug($slug); },
            function () use (&$hours): void { $hours = $this->hours->week(); },
            function () use (&$holidays, $live): void { $holidays = $live ? $this->holidays->upcoming() : $this->lastKnown->holidays; },
            function () use (&$weather, $live): void { $weather = $live ? $this->weather->week() : $this->lastKnown->weather; },
        );
        $res->withHeader('Server-Timing', sprintf('%s;dur=%.1f', $live ? 'live' : 'cached', (hrtime(true) - $started) / 1e6));
        if ($product === null) {
            throw new NotFoundException("No product $slug");
        }
        $today = new DateTimeImmutable('today');
        $lastDay = $today->modify('+' . PickupCalendar::DAYS_AHEAD . ' days')->format('Y-m-d');
        return new View('Shop/Product', new ProductPage(
            product: $product,
            days: PickupCalendar::days($today, $hours, $holidays ?? [], 3),
            closed: array_filter($holidays ?? [], fn (string $date) => $date <= $lastDay, ARRAY_FILTER_USE_KEY),
            holidaysKnown: $holidays !== null,
            weather: $weather,
        ));
    }

    #[Get('/cart')]
    public function cart(): View
    {
        return new View('Shop/Cart');
    }

    #[Get('/orders/{code}')]
    public function order(string $code): View
    {
        return new View('Shop/Order', $this->orders->byCode($code) ?? throw new NotFoundException('No such order'));
    }

    /** Where the checkout sends the browser: a message for the next page, then the order itself. */
    #[Get('/orders/{code}/thanks')]
    public function thanks(string $code): Redirect
    {
        $order = $this->orders->byCode($code) ?? throw new NotFoundException('No such order');
        $this->flash->success($this->translator->t('Thank you, {name}! Your order is reserved.', ['name' => $order->customerName]));
        return new Redirect("/orders/{$order->code}", 303);
    }
}

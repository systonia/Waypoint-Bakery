<?php

namespace Bakery\Shop;

use Bakery\Catalog\Products;
use Bakery\Ordering\Orders;
use Bakery\Pickup\{Holidays, OpeningHours, PickupCalendar, Weather};
use Closure;
use DateTimeImmutable;
use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Exceptions\NotFoundException;
use Waypoint\Flash\Flash;
use Waypoint\Http\{Redirect, Response, View};
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
    private Orders $orders;

    #[Inject]
    private Flash $flash;

    #[Inject]
    private Translator $translator;

    #[Get('/')]
    public function catalog(): View
    {
        return new View('Shop/Catalog', ['products' => $this->products->all()]);
    }

    /**
     * The product with its next pickup days, each with the weather. Four waits (two queries, two web services)
     * run at the same time with Tenma::all(), so the page takes as long as the slowest of them, not their sum.
     * Server-Timing shows each one in the browser's developer tools.
     */
    #[Get('/products/{slug}')]
    public function product(string $slug, Response $res): View
    {
        $timings = [];
        $timed = function (string $name, Closure $work) use (&$timings): Closure {
            return function () use ($name, $work, &$timings): mixed {
                $started = hrtime(true);
                try {
                    return $work();
                } finally {
                    $timings[$name] = (hrtime(true) - $started) / 1e6;
                }
            };
        };
        $started = hrtime(true);
        ['product' => $product, 'hours' => $hours, 'holidays' => $holidays, 'weather' => $weather] = \Tenma::all(
            product: $timed('product', fn () => $this->products->bySlug($slug)),
            hours: $timed('hours', fn () => $this->hours->week()),
            holidays: $timed('holidays', fn () => $this->holidays->upcoming()),
            weather: $timed('weather', fn () => $this->weather->week()),
        );
        $timings['total'] = (hrtime(true) - $started) / 1e6;
        $res->withHeader('Server-Timing', implode(', ', array_map(fn (string $name, float $ms) => sprintf('%s;dur=%.1f', $name, $ms), array_keys($timings), $timings)));

        if ($product === null) {
            throw new NotFoundException("No product $slug");
        }
        $today = new DateTimeImmutable('today');
        return new View('Shop/Product', [
            'product' => $product,
            'days' => PickupCalendar::days($today, $hours, $holidays ?? [], 3),
            'closed' => array_filter($holidays ?? [], fn (string $date) => $date <= $today->modify('+' . PickupCalendar::DAYS_AHEAD . ' days')->format('Y-m-d'), ARRAY_FILTER_USE_KEY),
            'holidaysKnown' => $holidays !== null,
            'weather' => $weather,
            'timings' => $timings,
        ]);
    }

    #[Get('/cart')]
    public function cart(): View
    {
        ['hours' => $hours, 'holidays' => $holidays] = \Tenma::all(
            hours: fn () => $this->hours->week(),
            holidays: fn () => $this->holidays->upcoming(),
        );
        return new View('Shop/Cart', ['days' => PickupCalendar::days(new DateTimeImmutable('today'), $hours, $holidays ?? [])]);
    }

    #[Get('/orders/{code}')]
    public function order(string $code): View
    {
        $order = $this->orders->byCode($code) ?? throw new NotFoundException('No such order');
        return new View('Shop/Order', ['order' => $order]);
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

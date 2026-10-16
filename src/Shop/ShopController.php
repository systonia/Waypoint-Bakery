<?php

namespace Bakery\Shop;

use Bakery\Catalog\Products;
use Bakery\Ordering\Orders;
use Bakery\Pickup\{Holidays, LastKnown, OpeningHours, PickupCalendar, Weather};
use Closure;
use DateTimeImmutable;
use Waypoint\Attributes\{Controller, Get, Ignore, Inject};
use Waypoint\Exceptions\NotFoundException;
use Waypoint\Flash\Flash;
use Waypoint\Http\{Redirect, Request, Response, View};
use Waypoint\I18n\Translator;

/** The storefront's pages. They are plain Waypoint views; the parts that react to clicks are live components in them. */
#[Controller]
#[Ignore]
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
     * The product with its next pickup days, each with the weather. Two versions side by side:
     *
     * - cached (the default): the holidays and the weather come from this worker's memory (LastKnown, refreshed by
     *   timers in index.php), so the page only waits for Postgres;
     * - live (?live): all four sources are fetched on this request with Tenma::all(), so the page takes as long as
     *   the slowest of them, not their sum.
     *
     * Server-Timing says which version ran and how long each source took ("cache" for the ones from memory).
     */
    #[Get('/products/{slug}')]
    public function product(string $slug, Request $req, Response $res): View
    {
        $live = $req->query('live') !== null;
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
        $sources = [
            'product' => $timed('product', fn () => $this->products->bySlug($slug)),
            'hours' => $timed('hours', fn () => $this->hours->week()),
        ];
        if ($live) {
            $sources['holidays'] = $timed('holidays', fn () => $this->holidays->upcoming());
            $sources['weather'] = $timed('weather', fn () => $this->weather->week());
        }
        $started = hrtime(true);
        $loaded = \Tenma::all(...$sources);
        $timings['total'] = (hrtime(true) - $started) / 1e6;
        ['product' => $product, 'hours' => $hours] = $loaded;
        $holidays = $live ? $loaded['holidays'] : $this->lastKnown->holidays;
        $weather = $live ? $loaded['weather'] : $this->lastKnown->weather;
        $res->withHeader('Server-Timing', implode(', ', [
            'version;desc=' . ($live ? 'live' : 'cached'),
            ...array_map(fn (string $name, float $ms) => sprintf('%s;dur=%.1f', $name, $ms), array_keys($timings), $timings),
            ...($live ? [] : ['holidays;desc=cache', 'weather;desc=cache']),
        ]));

        if ($product === null) {
            throw new NotFoundException("No product $slug");
        }
        $today = new DateTimeImmutable('today');
        return new View('Shop/Product', new ProductPage(
            product: $product,
            days: PickupCalendar::days($today, $hours, $holidays ?? [], 3),
            closed: array_filter($holidays ?? [], fn (string $date) => $date <= $today->modify('+' . PickupCalendar::DAYS_AHEAD . ' days')->format('Y-m-d'), ARRAY_FILTER_USE_KEY),
            holidaysKnown: $holidays !== null,
            weather: $weather,
            live: $live,
            timings: $timings,
        ));
    }

    #[Get('/cart')]
    public function cart(): View
    {
        ['hours' => $hours, 'holidays' => $holidays] = \Tenma::all(
            hours: fn () => $this->hours->week(),
            holidays: fn () => $this->holidays->upcoming(),
        );
        return new View('Shop/Cart', PickupCalendar::days(new DateTimeImmutable('today'), $hours, $holidays ?? []));
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

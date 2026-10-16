# Sonnenkorn, the Waypoint bakery

Sonnenkorn is a made-up bakery in Freiburg with an online shop: customers put together an order, pick a day and collect it in the shop. The people in the bakery see new orders arrive on a board, move them through the oven, keep the stock and look at the week's sales.

It is the example application of [Tenma](https://github.com/systonia/Tenma), [Waypoint](https://github.com/systonia/Waypoint), [Waypoint Live](https://github.com/systonia/Waypoint-Live) and the Waypoint plugins, and the running example of their documentation. It is small enough to read in an evening and real enough to run a shop:

- **Storefront** in English and German: catalog, product page, cart and checkout, order page.
- **Product page** with the public holidays from [date.nager.at](https://date.nager.at) and the pickup-day weather from [open-meteo.com](https://open-meteo.com), in two versions to compare: from the worker's memory, refreshed in the background (the default), or fetched on every request together with the product from Postgres (`?live`). Both carry on when a service is down.
- **Live order page**: the customer sees each step (received, in the oven, ready, picked up) the moment the bakery takes it.
- **Back office** behind a sign-in with two roles: a live order board, the stock, sales charts, exports as CSV and XLSX, a JSON API with an OpenAPI document.
- **Background jobs** in the same process: unconfirmed orders are cancelled after 15 minutes and their stock freed; the sales for the charts are aggregated every five minutes; the weather is refreshed every 15 minutes and the holidays once a day.

```bash
tenma --watch index.php      # http://localhost:8080, back office at /backoffice
```

## Setup

You need PHP 8.5 with Composer, [Tenma](https://github.com/systonia/Tenma/blob/main/docs/index.md#install) and Postgres on localhost (any recent version). The [Tailwind CSS standalone CLI](https://github.com/tailwindlabs/tailwindcss/releases) as `tailwindcss` in your `PATH` rebuilds the stylesheet while you work; the built `public/app.css` is in the repository, so it is optional.

```bash
git clone https://github.com/systonia/Waypoint-Bakery.git && cd Waypoint-Bakery
composer install
createdb bakery
tenma bin/task.php db:reset      # tables from database/migrations, demo data from database/seed.sql
tenma --watch index.php
```

Open <http://localhost:8080>. The back office is at <http://localhost:8080/backoffice>:

| Email | Password | Role |
|---|---|---|
| `anna@sonnenkorn.test` | `croissant` | owner: everything, including sales and exports |
| `ben@sonnenkorn.test` | `pretzel` | staff: order board and stock |

The database is `postgres://localhost/bakery` as your own user unless `DATABASE_URL` says otherwise. `tenma bin/task.php db:migrate` applies new migrations, `db:seed` loads the demo data into an empty database.

To try several workers, the way the shop runs in production:

```bash
tenma --workers 2 index.php
```

Open an order page in one browser and the board in another: whichever worker serves them, a click on the board moves the customer's page on.

## A tour

Each part of the shop, the files it lives in, and the page of the documentation that explains what it uses.

### Startup: one script, run once per worker

| What | Where | Docs |
|---|---|---|
| The app: plugins, options, the Postgres pool, middlewares, controllers | [`app.php`](app.php) | Waypoint [Application setup](https://github.com/systonia/Waypoint/blob/main/docs/guides/application.md), [Plugins](https://github.com/systonia/Waypoint/blob/main/docs/guides/plugins.md) |
| Serving it, the order feed's subscription, the background timers | [`index.php`](index.php) | Waypoint [Running under Tenma](https://github.com/systonia/Waypoint/blob/main/docs/guides/tenma.md), Tenma [Serving HTTP](https://github.com/systonia/Tenma/blob/main/docs/guides/http.md) |
| Settings from `.env`, `config.json` and `config.<APP_ENV>.json` | [`config.json`](config.json), [`.env.example`](.env.example) | Waypoint [Environment and .env files](https://github.com/systonia/Waypoint/blob/main/docs/guides/application.md#environment-and-env-files) |
| Tailwind on every save | [`tenma.dev.php`](tenma.dev.php), [`assets/app.css`](assets/app.css) | Tenma [Watch mode and build hooks](https://github.com/systonia/Tenma/blob/main/docs/guides/watch.md) |
| Migrations and demo data as CLI tasks | [`src/Database/DatabaseTasks.php`](src/Database/DatabaseTasks.php), [`bin/task.php`](bin/task.php), [`database/`](database) | Waypoint [CLI tasks](https://github.com/systonia/Waypoint/blob/main/docs/guides/cli-tasks.md), Tenma [Databases](https://github.com/systonia/Tenma/blob/main/docs/guides/database.md) |

### Storefront

| What | Where | Docs |
|---|---|---|
| Pages as controllers and views | [`src/Shop/ShopController.php`](src/Shop/ShopController.php), [`views/Shop/`](views/Shop), [`views/_Layout.php`](views/_Layout.php) | Waypoint [Routing](https://github.com/systonia/Waypoint/blob/main/docs/guides/routing.md), [Views](https://github.com/systonia/Waypoint/blob/main/docs/guides/views.md) |
| Queries that wait without blocking the worker | [`src/Catalog/Products.php`](src/Catalog/Products.php), [`src/Ordering/Orders.php`](src/Ordering/Orders.php) | Tenma [Databases and messages](https://github.com/systonia/Tenma/blob/main/docs/guides/database.md) |
| The product page, cached: holidays and weather from the worker's memory | `ShopController::product()`, [`src/Pickup/LastKnown.php`](src/Pickup/LastKnown.php), timers in [`index.php`](index.php) | Tenma [Timers](https://github.com/systonia/Tenma/blob/main/docs/guides/async.md#timers) |
| The product page, live (`?live`): four sources at once, `Server-Timing` and the timing note in development | `ShopController::product()`, [`views/Shop/Product.php`](views/Shop/Product.php) | Tenma [Fibers and asynchronous work](https://github.com/systonia/Tenma/blob/main/docs/guides/async.md#several-things-at-once) |
| Web services with a timeout and a fallback | [`src/Pickup/Holidays.php`](src/Pickup/Holidays.php), [`src/Pickup/Weather.php`](src/Pickup/Weather.php) | Tenma [Outgoing HTTP](https://github.com/systonia/Tenma/blob/main/docs/guides/fetch.md#errors) |
| Pickup days: open, not a holiday, within a week | [`src/Pickup/PickupCalendar.php`](src/Pickup/PickupCalendar.php) | |
| English and German, `?lang=de`, a cookie, the browser's language | [`lang/de.json`](lang/de.json), `$this->t()` in [`views/`](views) | I18n [Writing translatable text](https://github.com/systonia/Waypoint-I18n/blob/main/docs/guides/translating-text.md), [The locale of a request](https://github.com/systonia/Waypoint-I18n/blob/main/docs/guides/locale.md) |
| Live components in plain pages: cart badge, add to cart, cart lines | [`src/Shop/CartBadge.php`](src/Shop/CartBadge.php), [`AddToCart.php`](src/Shop/AddToCart.php), [`CartEditor.php`](src/Shop/CartEditor.php) and their `.Template.php` | Live [Components](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/components.md), [Templates](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/templates.md), [Events and actions](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/events.md) |
| One cart per tab, kept across pages under a cookie | [`src/Shop/Cart.php`](src/Shop/Cart.php) (`#[Scoped]`), [`src/Shop/CartCookie.php`](src/Shop/CartCookie.php) (a middleware) | Live [Services](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/services.md), Waypoint [Middleware](https://github.com/systonia/Waypoint/blob/main/docs/guides/middleware.md) |
| Checkout: a live form validated with Waypoint's attributes, then off to the order page | [`src/Shop/Checkout.php`](src/Shop/Checkout.php), [`src/Shop/CheckoutForm.php`](src/Shop/CheckoutForm.php) | Live [Forms](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/forms.md), [Navigation](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/navigation.md), Waypoint [Binding and validation](https://github.com/systonia/Waypoint/blob/main/docs/guides/binding.md) |
| Reserving stock in one transaction, the last croissant for one customer only | `Orders::place()` | Tenma [Transactions](https://github.com/systonia/Tenma/blob/main/docs/guides/database.md#transactions) |
| "Thank you" after the checkout | `ShopController::thanks()` | Flash [Queueing messages](https://github.com/systonia/Waypoint-Flash/blob/main/docs/guides/messages.md) |
| The order page that follows the bakery | [`src/Shop/OrderTracker.php`](src/Shop/OrderTracker.php), [`src/Ordering/OrderFeed.php`](src/Ordering/OrderFeed.php) | Live [Timers, broadcasts and server push](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/push.md#several-workers), Tenma [Messages between workers](https://github.com/systonia/Tenma/blob/main/docs/guides/database.md#messages-between-workers) |

### Back office

| What | Where | Docs |
|---|---|---|
| Sign-in form, JWT cookie, CSRF, flash messages | [`src/Backoffice/SessionController.php`](src/Backoffice/SessionController.php), [`views/Backoffice/Login.php`](views/Backoffice/Login.php) | Waypoint [Authentication](https://github.com/systonia/Waypoint/blob/main/docs/guides/authentication.md) |
| `#[Authenticated]` for the bakery, `#[Role('owner')]` for sales and exports | [`src/Backoffice/BackofficeController.php`](src/Backoffice/BackofficeController.php) | Waypoint [Guarding routes](https://github.com/systonia/Waypoint/blob/main/docs/guides/authentication.md#guarding-routes) |
| The live order board, which checks the user itself | [`src/Backoffice/OrderBoard.php`](src/Backoffice/OrderBoard.php) | Live [The connection and the user](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/connection.md), [Security model](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/security.md) |
| Stock, saved field by field | [`src/Backoffice/StockEditor.php`](src/Backoffice/StockEditor.php) | Live [Forms and binding](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/forms.md#fields-that-change-on-their-own) |
| Sales charts | `BackofficeController::sales()`, [`views/Backoffice/Sales.php`](views/Backoffice/Sales.php), [`src/Backoffice/SalesReport.php`](src/Backoffice/SalesReport.php) | Charts [Building charts](https://github.com/systonia/Waypoint-Charts/blob/main/docs/guides/charts.md) |
| Orders as a table, `orders.csv` and `orders.xlsx` | `BackofficeController::export()` | Export [Building a sheet](https://github.com/systonia/Waypoint-Export/blob/main/docs/guides/sheets.md), [Tables in views](https://github.com/systonia/Waypoint-Export/blob/main/docs/guides/views.md) |
| JSON API with bearer tokens, `/openapi/swagger.html` | [`src/Api/ApiController.php`](src/Api/ApiController.php) | Waypoint [OpenAPI](https://github.com/systonia/Waypoint/blob/main/docs/guides/openapi.md) |
| Cancelling unconfirmed orders, aggregating sales, refreshing weather and holidays | `Orders::expireUnconfirmed()`, `SalesReport::aggregate()`, `LastKnown`, timers in [`index.php`](index.php) | Tenma [Timers](https://github.com/systonia/Tenma/blob/main/docs/guides/async.md#timers) |

## Why a long-running process

Tenma runs `index.php` once per worker and keeps what it built: the routes and the container, compiled templates, the translations, a pool of open Postgres connections. Every request then runs in a fiber of its own, and while one waits, the worker serves others. The bakery leans on that in five places.

**Memory between requests.** The product page shows the pickup days with the public holidays and the weather. Both come from web services that need anything from 20 ms to over a second to answer, and both change rarely: the forecast a few times an hour, the holidays a few times a year. So each worker keeps their last good answer in memory ([`LastKnown`](src/Pickup/LastKnown.php)) and refreshes it in the background: `index.php` loads both once at start, in a task so that serving begins at once, then sets two timers with `Tenma::setInterval()` (weather every 15 minutes, holidays once a day; `WEATHER_INTERVAL` and `HOLIDAYS_INTERVAL` in `config.json`). The page reads from memory and only waits for Postgres. Until the first answer arrives, or while a service is down, it shows the last good data, or the note that the forecast or the holidays are not available. This is the normal choice in a long-running process: the memory survives between requests, the refresh runs in the same process as the pages, and no external cache service is needed.

**Waiting for several things at once.** The page can also fetch everything on the request, as `/products/<slug>?live` does: the product, the opening hours, the holidays and the forecast. In a fiber each of them is one call that simply returns its result; `Tenma::all()` starts all four together, so the page takes as long as the slowest source, not the sum. A service that is down costs at most its timeout (`API_TIMEOUT`, 2.5 s) and the page says so instead of failing.

**Comparing the two.** Both versions run side by side: open `/products/croissant` and `/products/croissant?live`. `Server-Timing` in the browser's developer tools says which version ran (`version;desc=cached` or `live`) and the time per source, `cache` for the ones read from memory. Under `tenma --watch` the page prints it too:

```text
Cached: loaded in 1 ms: product 1 ms, opening hours 1 ms, holidays and weather from memory. Add ?live to fetch them on this request.
Live: loaded in 454 ms with Tenma::all(): product 1 ms, opening hours 1 ms, holidays 14 ms, weather 454 ms. One after another: about 470 ms.
```

`tenma bin/bench.php 20` measures three ways against the real services: cached, live with `Tenma::all()`, and live one after another. On a day when open-meteo answered in about 450 ms:

```text
20 rounds, median
  cached:               1.3 ms
  live, together:     451.9 ms
  live, one by one:   473.9 ms
```

When the services are quick, live with `Tenma::all()` takes about 25 ms against 45 ms one after another, and the cached page still about 1 ms.

**State between clicks.** A live component is an object in the worker's memory for as long as its tab is open. The cart, the form with its errors and the board need no session store and no client-side code: the browser sends the click over its WebSocket, the method runs, the new HTML goes back.

**Pushing changes to open tabs.** When an order changes, the customer's page and every board show it at once. The WebSocket of each tab stays open in the process that serves it, so the server can send without being asked.

**Jobs next to the requests.** Cancelling stale orders and aggregating the sales are timers in `index.php`. They use the same services and the same connection pool as the pages, and need no scheduler of their own.

## Several workers

With `tenma --workers N`, every worker runs `index.php` with its own memory: its own tabs, its own copy of every service. Two things follow.

**Order changes reach every worker through `Tenma::publish()`.** Whoever changes an order (the checkout, the board, the API, the expiry job) calls `OrderFeed::announce($id)`. That publishes the id on the `orders` channel; in every worker the subscription from `index.php` reads the order again from Postgres and renders that worker's tabs with `Broadcast::changedHere()`. The message only says which order changed; Postgres stays the only truth. Postgres' own `LISTEN`/`NOTIFY` is not used: Tenma's pool cleans each connection after every query (`UNLISTEN *` among other things), so a `LISTEN` would not outlive its query, and the client has no call for notifications. For several Tenma processes behind a load balancer, `tenma --pubsub redis://host` carries the same messages through Redis, without a change to the code.

**The jobs are safe to run in every worker at once.** Each worker has both timers, so the jobs are written for that:

- `Orders::expireUnconfirmed()` cancels with one `UPDATE ... WHERE status = 'pending' ... RETURNING id`. Postgres locks the rows it changes; a second worker at the same moment waits, then finds those orders cancelled already. Each order is cancelled and restocked exactly once.
- `SalesReport::aggregate()` first claims the run: it moves the job's `last_run` in the `jobs` table forward only if the last run is older than the interval. Exactly one worker gets the row back and recomputes the sales; the others skip. That holds across processes too.

## Configuration

Settings come from Waypoint's `Environment`, in this order, the later one winning: the process environment, `.env`, `.env.<APP_ENV>`, `config.json`, `config.<APP_ENV>.json`, `config.local.json`.

| File | In git | Holds |
|---|---|---|
| `config.json` | yes | the bakery's settings: location, holiday region, minutes until an order expires, job and refresh intervals |
| `config.development.json` | yes | secrets that are only good for development; `tenma --watch` is `APP_ENV=development` |
| `config.test.json` | yes | the test database and secrets |
| `.env` | no | `JWT_SECRET`, `CSRF_SECRET` (`php -r 'echo bin2hex(random_bytes(32)), "\n";'`), `APP_ENV`, `DATABASE_URL` in production |

Since files win over the process environment, `config.json` holds nothing that a deployment sets itself: `PORT` and `DATABASE_URL` come from the environment (or `.env`), with `8080` and `postgres://localhost/bakery` as defaults in the code.

| Setting | Default | |
|---|---|---|
| `DATABASE_URL` | `postgres://localhost/bakery` | |
| `PORT` | `8080` | |
| `HOLIDAYS_URL`, `WEATHER_URL` | the public services | the tests point them at a fake |
| `API_TIMEOUT` | `2.5` | seconds per web service |
| `UNCONFIRMED_MINUTES` | `15` | how long a reservation waits for its confirmation |
| `EXPIRY_INTERVAL`, `SALES_INTERVAL` | `30`, `300` | seconds between the jobs' runs; `0` turns a job off |
| `WEATHER_INTERVAL`, `HOLIDAYS_INTERVAL` | `900`, `86400` | seconds between refreshes of the weather and the holidays in memory |
| `OPENAPI` | `true` | serve `/openapi/spec.json` and `/openapi/swagger.html` |

## Tests

```bash
createdb bakery_test
vendor/bin/phpunit
vendor/bin/phpstan
vendor/bin/live-check
```

The app waits for Postgres and web services with Tenma's own functions, which only run inside Tenma's event loop. So `tests/bootstrap.php` hands the PHPUnit run to `tenma tests/tenma.php`, which runs PHPUnit in a task and, on a free port of the same process, a fake of the two web services ([`tests/ApiStub.php`](tests/ApiStub.php)): fast, 300 ms late, or down. It counts its requests, so the tests can check that the cached product page does not call it and `?live` does. The tests never reach the real services.

- [`StorefrontTest`](tests/StorefrontTest.php), [`OrderingTest`](tests/OrderingTest.php), [`BackofficeTest`](tests/BackofficeTest.php), [`ApiTest`](tests/ApiTest.php) send requests through the app with Waypoint's testing kit and drive live components with `Waypoint\Live\Testing\Tab`, on the database `bakery_test` with fresh demo data for each test.
- [`SiteTest`](tests/SiteTest.php) starts `tenma --workers 2 index.php` and plays customer, bakery and till over HTTP and the live WebSocket: the order placed in one tab appears on the board, and the order page in two tabs (on both workers) follows each step.
- [`PickupCalendarTest`](tests/PickupCalendarTest.php) needs nothing but PHP.

`TENMA_BIN` points the tests at another tenma binary; `SITE_LOG=/tmp/site.log` keeps the log of the server `SiteTest` starts.

## Layout

```text
app.php, index.php       the app, and serving it under Tenma
bin/task.php             CLI tasks under tenma: db:reset, i18n:extract, ...
bin/bench.php            the product page's sources: cached, live with Tenma::all(), live one after another
database/                migrations and demo data
lang/de.json             the German texts; English is in the code
src/Catalog              products
src/Pickup               opening hours, holidays, weather, pickup days
src/Ordering             orders, their status, and the feed that keeps every worker's tabs current
src/Shop                 storefront pages and live components
src/Backoffice           sign-in, board, stock, sales
src/Api                  the JSON API
views/                   pages and layouts
tests/                   PHPUnit, run under tenma
```

## License

MIT or Apache-2.0, at your option: [LICENSE-MIT](LICENSE-MIT), [LICENSE-APACHE](LICENSE-APACHE).

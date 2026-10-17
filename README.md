# Sonnenkorn, the Waypoint bakery

Sonnenkorn is a made-up bakery in Freiburg with an online shop: customers put together an order, pick a day and collect it in the shop. The people in the bakery see new orders arrive on a board, move them through the oven, keep the stock and look at the sales. It is the example application of [Tenma](https://github.com/systonia/Tenma), [Waypoint](https://github.com/systonia/Waypoint), [Waypoint Live](https://github.com/systonia/Waypoint-Live) and the Waypoint plugins.

Four things it shows:

- **The product page answers in about 1 ms from the worker's memory.** It needs the public holidays and the weather from two web services. Timers in [`index.php`](index.php) keep their last good answers in memory ([`LastKnown`](src/Services/LastKnown.php)), so the page only waits for Postgres. `/products/croissant?live` fetches all four sources on the request with `Tenma::all()` and takes as long as the slowest: 18 ms on a quick day, 450 ms when open-meteo was slow. `Server-Timing` says which version ran and how long it took ([`ShopController::product()`](src/Controllers/ShopController.php)).
- **The board and the customer's order page follow each other across `tenma --workers 2`.** A click on the board moves the customer's page on, whichever worker serves each tab: `Broadcast::changed()` from Waypoint Live goes out through `Tenma::publish()`, with no message broker. [`SiteTest`](tests/SiteTest.php) plays it through on two workers.
- **The background jobs are timers in `index.php`, safe in every worker.** Each is one SQL statement: [`Orders::expireUnconfirmed()`](src/Repositories/Orders.php) cancels stale reservations and frees their stock, [`SalesReport::aggregate()`](src/Repositories/SalesReport.php) claims its run and writes the sales in the same statement.
- **Cart, checkout and board have no hand-written JavaScript.** They are live components: a PHP class and a template each, in [`src/Components`](src/Components).

The whole app is 2,350 lines of PHP in 63 files (`src`, `views`, `app.php`, `index.php`), with 940 lines of tests.

## Setup

You need PHP 8.5 with Composer, [Tenma](https://github.com/systonia/Tenma/blob/main/docs/index.md#install) and Postgres on localhost. The [Tailwind CSS standalone CLI](https://github.com/tailwindlabs/tailwindcss/releases) as `tailwindcss` in your `PATH` rebuilds the stylesheet while you work; the built `public/app.css` is in the repository, so it is optional.

```bash
git clone https://github.com/systonia/Waypoint-Bakery.git && cd Waypoint-Bakery
composer install
echo APP_ENV=development > .env      # the development secrets in config.development.json
createdb bakery
tenma index.php db:reset             # tables from database/migrations, demo data from database/seed.sql
tenma --watch index.php
```

Open <http://localhost:8080>. The back office is at <http://localhost:8080/backoffice>:

| Email | Password | Role |
|---|---|---|
| `anna@sonnenkorn.test` | `croissant` | owner: everything, including sales and exports |
| `ben@sonnenkorn.test` | `pretzel` | staff: order board and stock |

`tenma index.php db:migrate` applies new migrations. The database is `postgres://localhost/bakery` unless `DATABASE_URL` says otherwise. Settings come from `config.json`, `config.<APP_ENV>.json`, `.env` and the process environment, as Waypoint's [Environment](https://github.com/systonia/Waypoint/blob/main/docs/guides/application.md#environment-and-env-files) reads them; a deployment sets `APP_ENV`, `JWT_SECRET`, `CSRF_SECRET` and `DATABASE_URL` (see [`.env.example`](.env.example)). A missing secret stops the app at boot.

With several workers, the way the shop runs in production:

```bash
tenma --workers 2 index.php
```

Tests, static analysis and the checks of views and templates:

```bash
createdb bakery_test
vendor/bin/phpunit
vendor/bin/phpstan
vendor/bin/waypoint-check
```

`tests/Support/bootstrap.php` hands the PHPUnit run to `tenma tests/Support/tenma.php`, which serves a fake of the two web services ([`tests/Support/ApiStub.php`](tests/Support/ApiStub.php)) on a free port of the same process: fast, 300 ms late, down, or answering no JSON. `SiteTest` starts `tenma --workers 2 index.php`; `SITE_LOG=/tmp/site.log` keeps its log. The other tests sit in the folder of the class they test, under the same name with `Test`.

## Layout

The code is sorted by layer, one folder per kind of class, and each namespace follows its folder (`Bakery\Controllers\ShopController` is `src/Controllers/ShopController.php`):

| Folder | What is in it |
|---|---|
| [`src/Controllers`](src/Controllers) | The routes: shop pages, sign-in, back office, the JSON API |
| [`src/Components`](src/Components) | The live components, each with its `.Template.php` next to it |
| [`src/Models`](src/Models) | Data: orders, products, cart lines, pickup days, the sales figures, and the view models of the product and sales pages |
| [`src/Repositories`](src/Repositories) | Every SQL statement of the app, one class per part of the database |
| [`src/Services`](src/Services) | The cart, the pickup calendar, the web services and their last answers, formatting |
| [`src/Requests`](src/Requests) | The DTOs that `#[Body]` binds and validates |
| [`src/Responses`](src/Responses) | What the API returns besides models |
| [`src/Tasks`](src/Tasks) | The CLI tasks: migrations and demo data |
| [`views`](views) | Pages and layouts; `_Layout.php` for the shop, `_Backoffice.php` for the back office |
| [`database`](database) | Migrations and the demo data |
| [`tests`](tests) | The same folders as `src`, plus `Support` with the test case, the live WebSocket client and the fake web services |

## A tour

Each part of the shop, the files it lives in, and the page of the documentation that explains what it uses.

### Startup

| What | Where | Docs |
|---|---|---|
| The app: plugins, options, the Postgres pool, middlewares, controllers | [`app.php`](app.php) | Waypoint [Application setup](https://github.com/systonia/Waypoint/blob/main/docs/guides/application.md) |
| Serving it, the background jobs as timers, CLI tasks with arguments | [`index.php`](index.php) | Tenma [Timers](https://github.com/systonia/Tenma/blob/main/docs/guides/async.md#timers), Waypoint [CLI tasks](https://github.com/systonia/Waypoint/blob/main/docs/guides/cli-tasks.md) |
| Migrations and demo data as CLI tasks | [`src/Tasks/DatabaseTasks.php`](src/Tasks/DatabaseTasks.php), [`database/`](database) | Tenma [Databases](https://github.com/systonia/Tenma/blob/main/docs/guides/database.md) |
| Tailwind on every save | [`tenma.dev.php`](tenma.dev.php), [`assets/app.css`](assets/app.css) | Tenma [Watch mode and build hooks](https://github.com/systonia/Tenma/blob/main/docs/guides/watch.md) |

### Storefront

| What | Where | Docs |
|---|---|---|
| Pages as controllers and views with typed models (`@model`); `{{ }}` escapes | [`src/Controllers/ShopController.php`](src/Controllers/ShopController.php), [`views/Shop/`](views/Shop), [`views/_Layout.php`](views/_Layout.php) | Waypoint [Routing](https://github.com/systonia/Waypoint/blob/main/docs/guides/routing.md), [Views](https://github.com/systonia/Waypoint/blob/main/docs/guides/views.md) |
| Rows and web service answers read with `Row`'s typed getters, no casts, PHPStan level 10 | [`src/Models/Product.php`](src/Models/Product.php), [`src/Repositories/Orders.php`](src/Repositories/Orders.php), [`src/Services/Weather.php`](src/Services/Weather.php) | Tenma [Databases](https://github.com/systonia/Tenma/blob/main/docs/guides/database.md), Waypoint [Types and PHPStan](https://github.com/systonia/Waypoint/blob/main/docs/guides/types.md) |
| The product page from memory, or `?live` with four sources at once | `ShopController::product()`, [`src/Services/LastKnown.php`](src/Services/LastKnown.php) | Tenma [Several things at once](https://github.com/systonia/Tenma/blob/main/docs/guides/async.md#several-things-at-once) |
| Web services with a timeout and a fallback | [`src/Services/Holidays.php`](src/Services/Holidays.php), [`src/Services/Weather.php`](src/Services/Weather.php) | Tenma [Outgoing HTTP](https://github.com/systonia/Tenma/blob/main/docs/guides/fetch.md#errors) |
| Pickup days: open, not a holiday, within a week | [`src/Services/PickupCalendar.php`](src/Services/PickupCalendar.php) | |
| English and German, `?lang=de`, a cookie, the browser's language | [`lang/de.json`](lang/de.json), `$this->t()` in views and templates | I18n [Writing translatable text](https://github.com/systonia/Waypoint-I18n/blob/main/docs/guides/translating-text.md) |
| Live components in plain pages: cart badge, add to cart, cart lines | [`CartBadge.php`](src/Components/CartBadge.php), [`AddToCart.php`](src/Components/AddToCart.php), [`CartEditor.php`](src/Components/CartEditor.php) and their `.Template.php` | Live [Components](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/components.md), [Events and actions](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/events.md) |
| One cart per tab, kept across pages under a cookie | [`src/Services/Cart.php`](src/Services/Cart.php) (`#[Scoped]`, saved by [`Carts`](src/Repositories/Carts.php)), [`src/Services/CartCookie.php`](src/Services/CartCookie.php) (a middleware) | Live [Services](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/services.md), Waypoint [Middleware](https://github.com/systonia/Waypoint/blob/main/docs/guides/middleware.md) |
| Checkout: the form arrives as a validated DTO, then off to the order page | [`src/Components/Checkout.php`](src/Components/Checkout.php), [`src/Requests/CheckoutForm.php`](src/Requests/CheckoutForm.php) | Live [Forms](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/forms.md#a-dto-for-the-form), Waypoint [Binding and validation](https://github.com/systonia/Waypoint/blob/main/docs/guides/binding.md) |
| Reserving stock in one transaction, the last croissant for one customer only | `Orders::place()` | Tenma [Transactions](https://github.com/systonia/Tenma/blob/main/docs/guides/database.md#transactions) |
| "Thank you" after the checkout | `ShopController::thanks()` | Flash [Queueing messages](https://github.com/systonia/Waypoint-Flash/blob/main/docs/guides/messages.md) |
| The order page that follows the bakery, on every worker | [`src/Components/OrderTracker.php`](src/Components/OrderTracker.php), [`OrderTracker.Template.php`](src/Components/OrderTracker.Template.php) | Live [Timers, broadcasts and server push](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/push.md#several-workers) |

### Back office

| What | Where | Docs |
|---|---|---|
| Sign-in form with `#[Body(validate: false)]`, `JWT::signIn()`, CSRF, flash messages | [`src/Controllers/SessionController.php`](src/Controllers/SessionController.php), [`views/Backoffice/Login.php`](views/Backoffice/Login.php) | Waypoint [Authentication](https://github.com/systonia/Waypoint/blob/main/docs/guides/authentication.md) |
| `#[Authenticated]` for the bakery, `#[Role('owner')]` for sales and exports; the layout reads the user from `$this->request` | [`src/Controllers/BackofficeController.php`](src/Controllers/BackofficeController.php), [`views/_Backoffice.php`](views/_Backoffice.php) | Waypoint [Guarding routes](https://github.com/systonia/Waypoint/blob/main/docs/guides/authentication.md#guarding-routes) |
| The live order board and the stock, guarded by `#[Authenticated]` on the component | [`src/Components/OrderBoard.php`](src/Components/OrderBoard.php), [`src/Components/StockEditor.php`](src/Components/StockEditor.php) | Live [Guards on components and actions](https://github.com/systonia/Waypoint-Live/blob/main/docs/guides/connection.md#guards-on-components-and-actions) |
| Sales charts in the visitor's language | `BackofficeController::sales()`, [`src/Repositories/SalesReport.php`](src/Repositories/SalesReport.php), [`views/Backoffice/Sales.php`](views/Backoffice/Sales.php) | Charts [Building charts](https://github.com/systonia/Waypoint-Charts/blob/main/docs/guides/charts.md) |
| Orders as a table, `orders.csv` and `orders.xlsx` | `BackofficeController::export()` | Export [Building a sheet](https://github.com/systonia/Waypoint-Export/blob/main/docs/guides/sheets.md) |
| JSON API with bearer tokens at `/v1`, documented at `/openapi/swagger.html` from the return types | [`src/Controllers/ApiController.php`](src/Controllers/ApiController.php), [`OrderSummary.php`](src/Responses/OrderSummary.php), [`StatusChange.php`](src/Requests/StatusChange.php) | Waypoint [OpenAPI](https://github.com/systonia/Waypoint/blob/main/docs/guides/openapi.md) |

## License

MIT or Apache-2.0, at your option: [LICENSE-MIT](LICENSE-MIT), [LICENSE-APACHE](LICENSE-APACHE).

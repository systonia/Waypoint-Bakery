<?php

namespace Bakery\Tests;

use Bakery\Pickup\{OpeningHours, PickupCalendar};
use DateTimeImmutable;
use Waypoint\JWT;

/**
 * The app as it runs: `tenma --workers 2 index.php` on the test database, driven like browsers (HTTP and the live
 * WebSocket) and like the till (the API). Tenma hands new connections to the workers in turn, so the tabs below
 * sit on both workers, and an order change from one worker has to reach the other through Tenma::publish().
 */
final class SiteTest extends AppTestCase
{
    /** @var resource|null */
    private $server = null;

    private int $port = 0;

    /** @var array<string, string> the customer's browser cookies */
    private array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $probe = stream_socket_server('tcp://127.0.0.1:0') ?: throw new \RuntimeException('No free port');
        $this->port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $log = getenv('SITE_LOG') ?: '/dev/null';
        $server = proc_open(
            [getenv('TENMA_BIN') ?: 'tenma', '--workers', '2', __DIR__ . '/../index.php'],
            [1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            null,
            // Port 9 refuses connections: the holiday and weather services are "down", which the shop survives
            [...getenv(), 'APP_ENV' => 'test', 'PORT' => (string) $this->port, 'HOLIDAYS_URL' => 'http://127.0.0.1:9', 'WEATHER_URL' => 'http://127.0.0.1:9'],
        );
        $this->server = $server ?: throw new \RuntimeException('tenma did not start');
        for ($i = 0; $i < 100 && @fsockopen('127.0.0.1', $this->port) === false; $i++) {
            \Tenma::sleep(0.05);
        }
    }

    protected function tearDown(): void
    {
        if ($this->server !== null) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        parent::tearDown();
    }

    public function testAnOrderTravelsFromTheShopToTheBakeryAndBackAcrossWorkers(): void
    {
        $board = new LiveClient($this->port, ['session' => JWT::encode(['sub' => 2, 'name' => 'Ben', 'role' => 'staff'])]);
        $this->assertStringContainsString('Ready for pickup', $board->mount('app-order-board'));

        // A customer fills the cart on the catalog page and checks out on the cart page
        [$status] = $this->http('GET', '/');
        $this->assertSame(200, $status);
        $catalog = new LiveClient($this->port, $this->cookies);
        $catalog->mount('app-add-to-cart', ['product' => '4']);
        $catalog->call('add');
        $catalog->call('add');
        $this->assertStringContainsString('2 in your cart', $catalog->html['c0']);

        $this->http('GET', '/cart');
        $checkout = new LiveClient($this->port, $this->cookies);
        $days = PickupCalendar::days(new DateTimeImmutable('today'), $this->service(OpeningHours::class)->week(), []);
        $checkout->mount('app-checkout', ['days' => $days]);
        $checkout->call('place', [['name' => 'Emma', 'email' => 'emma@example.test', 'pickupDate' => $days[0]]]);
        $this->assertCount(1, $checkout->navigations);
        $this->assertMatchesRegularExpression('#^/orders/[0-9a-f]{10}/thanks$#', $checkout->navigations[0]);
        $code = explode('/', $checkout->navigations[0])[2];

        // The board, opened before the order existed, shows it without a reload
        $board->waitFor('/Emma.*2 × Butter croissant/s');

        // The customer's page, open in two tabs, follows every step the bakery takes
        [$status, , $body] = $this->http('GET', "/orders/$code");
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Please confirm the order', $body);
        $tabs = [new LiveClient($this->port, $this->cookies), new LiveClient($this->port, $this->cookies)];
        foreach ($tabs as $tab) {
            $tab->mount('app-order-tracker', ['code' => $code]);
        }
        $tabs[0]->call('confirm');
        foreach ($tabs as $tab) {
            $tab->waitFor('/aria-current="step"\s*>\s*Received/');
        }

        $token = JWT::encode(['sub' => 2, 'name' => 'Ben', 'role' => 'staff']);
        [$status] = $this->http('PATCH', "/v1/orders/$code", ['status' => 'baking'], ["Authorization: Bearer $token"]);
        $this->assertSame(200, $status);
        foreach ($tabs as $tab) {
            $tab->waitFor('/aria-current="step"\s*>\s*In the oven/');
        }
        $board->waitFor('/data-key="baking".*Emma/s');
    }

    /**
     * @param non-empty-string $method
     * @param array<string, mixed> $json the body; none when empty
     * @param list<string> $headers
     * @return array{int, array<string, string>, string} status, headers, body; the customer keeps the cookies
     */
    private function http(string $method, string $path, array $json = [], array $headers = []): array
    {
        $curl = curl_init("http://127.0.0.1:{$this->port}$path");
        $cookie = implode('; ', array_map(fn (string $name, string $value) => "$name=$value", array_keys($this->cookies), $this->cookies));
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ["Cookie: $cookie", 'Content-Type: application/json', ...$headers],
        ]);
        if ($json !== []) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($json, JSON_THROW_ON_ERROR));
        }
        $response = (string) curl_exec($curl);
        $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        preg_match_all('/^set-cookie: ([^=]+)=([^;]*)/mi', substr($response, 0, $size), $set, PREG_SET_ORDER);
        foreach ($set as [, $name, $value]) {
            $this->cookies[$name] = $value;
        }
        return [(int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE), [], substr($response, $size)];
    }
}

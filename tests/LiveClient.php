<?php

namespace Bakery\Tests;

use RuntimeException;
use Waypoint\Support\Row;

/** A browser tab's WebSocket to /_live, for tests against a running server: mount, call, and what comes back. */
final class LiveClient
{
    /** @var resource */
    private $socket;

    /** @var array<string, string> component id => its last HTML */
    public array $html = [];

    /** @var list<string> where the server sent the browser */
    public array $navigations = [];

    /** @param array<string, string> $cookies */
    public function __construct(int $port, array $cookies = [])
    {
        $socket = stream_socket_client("tcp://127.0.0.1:$port", timeout: 5) ?: throw new RuntimeException('No connection');
        stream_set_timeout($socket, 5);
        $this->socket = $socket;
        $cookie = implode('; ', array_map(fn (string $name, string $value) => "$name=$value", array_keys($cookies), $cookies));
        fwrite($socket, "GET /_live HTTP/1.1\r\nHost: 127.0.0.1:$port\r\nOrigin: http://127.0.0.1:$port\r\nCookie: $cookie\r\n"
            . "Upgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: " . base64_encode(random_bytes(16)) . "\r\nSec-WebSocket-Version: 13\r\n\r\n");
        $head = '';
        while (!str_contains($head, "\r\n\r\n") && ($line = fgets($socket)) !== false) {
            $head .= $line;
        }
        if ((int) substr($head, 9, 3) !== 101) {
            throw new RuntimeException("No WebSocket: $head");
        }
        $this->send(['t' => 'hello', 'token' => null]);
        $this->receive();
    }

    /** @param array<string, mixed> $inputs */
    public function mount(string $selector, array $inputs = [], string $id = 'c0'): string
    {
        $this->send(['t' => 'mount', 'id' => $id, 'c' => $selector, 'p' => (object) $inputs]);
        $this->receive();
        return $this->html[$id] ?? '';
    }

    /**
     * Calls a method the template wires up, and reads what comes back until nothing comes for a moment.
     *
     * @param list<mixed> $args
     */
    public function call(string $method, array $args = [], string $id = 'c0'): void
    {
        $this->send(['t' => 'call', 'id' => $id, 'm' => $method, 'a' => $args]);
        $this->receive();
        stream_set_timeout($this->socket, 0, 300_000);
        while ($this->receive() !== []) {
            // the renders and the navigation that followed
        }
        stream_set_timeout($this->socket, 5);
    }

    /** Reads what the server pushes until $html of the component matches, or fails after $seconds. */
    public function waitFor(string $pattern, string $id = 'c0', float $seconds = 5): string
    {
        $until = microtime(true) + $seconds;
        while (preg_match($pattern, $this->html[$id] ?? '') !== 1) {
            if (microtime(true) > $until || $this->receive() === []) {
                throw new RuntimeException("Nothing matched $pattern in $id:\n" . ($this->html[$id] ?? ''));
            }
        }
        return $this->html[$id];
    }

    public function close(): void
    {
        fclose($this->socket);
    }

    /** @param array<string, mixed> $message */
    private function send(array $message): void
    {
        $payload = json_encode($message, JSON_THROW_ON_ERROR);
        $length = strlen($payload);
        $mask = random_bytes(4);
        $header = "\x81" . match (true) {
            $length < 126 => chr(0x80 | $length),
            $length < 65536 => chr(0x80 | 126) . pack('n', $length),
            default => chr(0x80 | 127) . pack('J', $length),
        };
        fwrite($this->socket, $header . $mask . ($payload ^ str_repeat($mask, intdiv($length, 4) + 1)));
    }

    /**
     * One message; [] when none came in time.
     *
     * @return array<mixed>
     */
    private function receive(): array
    {
        $head = (string) fread($this->socket, 2);
        if (strlen($head) < 2) {
            return [];
        }
        $length = ord($head[1]) & 0x7f;
        if ($length === 126) {
            $length = Row::of(unpack('n', (string) fread($this->socket, 2)))->int('1');
        } elseif ($length === 127) {
            $length = Row::of(unpack('J', (string) fread($this->socket, 8)))->int('1');
        }
        $data = '';
        while (($missing = $length - strlen($data)) > 0 && !feof($this->socket)) {
            $data .= (string) fread($this->socket, $missing);
        }
        $message = json_decode($data, true);
        if (!is_array($message)) {
            return [];
        }
        if (is_string($message['id'] ?? null) && is_string($message['html'] ?? null)) {
            $this->html[$message['id']] = $message['html'];
        }
        if (($message['t'] ?? null) === 'navigate' && is_string($message['url'] ?? null)) {
            $this->navigations[] = $message['url'];
        }
        return $message;
    }
}

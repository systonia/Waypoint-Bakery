<?php
// PHPUnit inside Tenma's event loop, so tests can query Postgres and fetch. Next to it, on a free port of the
// same process, a fake of date.nager.at and api.open-meteo.com (ApiStub): the tests never reach the real ones.

require __DIR__ . '/../../vendor/autoload.php';

$probe = stream_socket_server('tcp://127.0.0.1:0') ?: throw new RuntimeException('No free port');
$port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
putenv("API_STUB=http://127.0.0.1:$port");
Tenma::serve($port, new Bakery\Tests\Support\ApiStub());

Tenma::async(fn () => exit((new PHPUnit\TextUI\Application())->run(Waypoint\Support\Row::of($_SERVER)->list('argv', 'string'))));

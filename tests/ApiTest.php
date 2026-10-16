<?php

namespace Bakery\Tests;

use Bakery\Ordering\Orders;
use DateTimeImmutable;

final class ApiTest extends AppTestCase
{
    public function testTheRangeIsPublic(): void
    {
        $this->get('/v1/products')->assertOk()
            ->assertJsonPath('2.slug', 'pretzel')
            ->assertJsonPath('2.name.de', 'Laugenbrezel')
            ->assertJsonPath('2.priceCents', 120);
    }

    public function testOrdersNeedAToken(): void
    {
        $this->get('/v1/orders')->assertStatus(401);
        $this->json('POST', '/v1/tokens', ['email' => 'ben@sonnenkorn.test', 'password' => 'wrong'])->assertStatus(401);
        $token = (string) $this->json('POST', '/v1/tokens', ['email' => 'ben@sonnenkorn.test', 'password' => 'pretzel'])->assertOk()->json('token');
        $this->get('/v1/orders', ['Authorization' => "Bearer $token"])->assertOk()->assertJson([]);
    }

    public function testMovingAnOrderOnOneStepAtATime(): void
    {
        $orders = $this->service(Orders::class);
        $order = $orders->place([1 => 1], 'Paul', 'paul@example.test', new DateTimeImmutable('tomorrow')->format('Y-m-d'), 'en');
        $orders->confirm($order->id);

        $this->request('PATCH', "/v1/orders/{$order->code}")->withJwt(['sub' => 2, 'role' => 'staff'])->withJson(['status' => 'ready'])->send()
            ->assertStatus(422)
            ->assertJsonPath('errors.status', 'The order is received; its next step is baking.');
        $this->request('PATCH', "/v1/orders/{$order->code}")->withJwt(['sub' => 2, 'role' => 'staff'])->withJson(['status' => 'baking'])->send()
            ->assertOk()
            ->assertJsonPath('status', 'baking')
            ->assertJsonPath('customer', 'Paul')
            ->assertJsonPath('lines.0.quantity', 1)
            ->assertJsonPath('lines.0.priceCents', $order->lines[0]->priceCents);
        $this->request('PATCH', '/v1/orders/nope')->withJwt(['sub' => 2, 'role' => 'staff'])->withJson(['status' => 'baking'])->send()
            ->assertStatus(404);
    }

    public function testTheOpenApiDocumentDescribesTheApi(): void
    {
        $spec = $this->get('/openapi/spec.json')->assertOk();
        $this->assertSame('Sonnenkorn bakery API', $spec->json('info.title'));
        $this->assertSame('Move an order to its next step', $spec->json('paths./v1/orders/{code}.patch.summary'));
        $this->assertSame('#/components/schemas/StatusChange', $spec->json('paths./v1/orders/{code}.patch.requestBody.content.application/json.schema.$ref'));
        // The pages are no API
        $this->assertNull($spec->json('paths./cart'));
    }
}

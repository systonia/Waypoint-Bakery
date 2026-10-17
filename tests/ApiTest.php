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
        $this->assertSame(['slug', 'name', 'description', 'category', 'priceCents', 'stock'], array_keys($this->get('/v1/products')->json('0')));
    }

    public function testOrdersNeedAToken(): void
    {
        $this->get('/v1/orders')->assertStatus(401);
        $this->json('POST', '/v1/tokens', ['email' => 'ben@sonnenkorn.test', 'password' => 'wrong'])->assertStatus(401);
        $token = $this->json('POST', '/v1/tokens', ['email' => 'ben@sonnenkorn.test', 'password' => 'pretzel'])->assertOk()->json('token');
        $this->get('/v1/orders', ['Authorization' => "Bearer $token"])->assertOk()->assertJson([]);
    }

    public function testMovingAnOrderOnOneStepAtATime(): void
    {
        $orders = $this->app()->get(Orders::class);
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
        $this->assertSame('#/components/schemas/OrderSummary', $spec->json('paths./v1/orders/{code}.patch.responses.200.content.application/json.schema.$ref'));
        $this->assertSame(['pending', 'received', 'baking', 'ready', 'picked_up', 'cancelled', null], $spec->json('components.schemas.StatusChange.properties.status.enum'));
        $this->assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Product']], $spec->json('paths./v1/products.get.responses.200.content.application/json.schema'));
        $this->assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrderSummary']], $spec->json('paths./v1/orders.get.responses.200.content.application/json.schema'));
        $this->assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OrderLine'], 'readOnly' => true], $spec->json('components.schemas.OrderSummary.properties.lines'));
        $this->assertSame(['slug', 'name', 'description', 'category', 'priceCents', 'stock'], array_keys($spec->json('components.schemas.Product.properties')));
        $this->assertSame(['/v1/orders', '/v1/orders/{code}', '/v1/products', '/v1/tokens'], array_keys($spec->json('paths')));
    }
}

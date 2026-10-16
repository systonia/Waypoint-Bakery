<?php

namespace Bakery\Api;

use Bakery\Backoffice\Users;
use Bakery\Catalog\{Product, Products};
use Bakery\Ordering\{Order, OrderFeed, OrderStatus, Orders};
use Waypoint\Attributes\{Authenticated, Body, Controller, Get, Inject, Patch, Post, SkipCsrf, Summary, Tags, Throws, Version};
use Waypoint\Exceptions\{NotFoundException, UnauthorizedException, ValidationException};
use Waypoint\JWT;

/**
 * The JSON API, for the till and the delivery app. Programs send a bearer token, not cookies, so CSRF does not
 * apply. The OpenAPI document is generated from these attributes: /openapi/swagger.html.
 */
#[Controller]
#[Version('v1')]
#[SkipCsrf]
final class ApiController
{
    #[Inject]
    private Users $users;

    #[Inject]
    private Products $products;

    #[Inject]
    private Orders $orders;

    #[Inject]
    private OrderFeed $feed;

    /** @return array{token: string} */
    #[Post('/tokens')]
    #[Tags(['Sign-in'])]
    #[Summary('Exchange email and password for a bearer token')]
    #[Throws(
        exception: UnauthorizedException::class,
        status: 401,
        description: 'Wrong email or password'
    )]
    public function token(#[Body] Credentials $credentials): array
    {
        $user = $this->users->verify($credentials->email, $credentials->password)
            ?? throw new UnauthorizedException('Wrong email or password.');
        return ['token' => JWT::encode(['sub' => $user['id'], 'name' => $user['name'], 'role' => $user['role']])];
    }

    /** @return list<array<string, mixed>> */
    #[Get('/products')]
    #[Tags(['Products'])]
    #[Summary('The range with prices in cents and what is left')]
    public function products(): array
    {
        return array_map(fn (Product $p) => [
            'slug' => $p->slug,
            'name' => $p->name,
            'category' => $p->category,
            'priceCents' => $p->priceCents,
            'stock' => $p->stock,
        ], $this->products->all());
    }

    /** @return list<array<string, mixed>> */
    #[Get('/orders')]
    #[Authenticated]
    #[Tags(['Orders'])]
    #[Summary('Every order that is not picked up or cancelled')]
    public function openOrders(): array
    {
        return array_map(self::json(...), $this->orders->open());
    }

    /** @return array<string, mixed> */
    #[Get('/orders/{code}')]
    #[Authenticated]
    #[Tags(['Orders'])]
    #[Summary('One order')]
    #[Throws(
        exception: NotFoundException::class,
        status: 404
    )]
    public function order(string $code): array
    {
        return self::json($this->orders->byCode($code) ?? throw new NotFoundException("No order $code"));
    }

    /**
     * Moves the order one step on; the customer's page and every order board show it at once.
     *
     * @return array<string, mixed>
     */
    #[Patch('/orders/{code}')]
    #[Authenticated]
    #[Tags(['Orders'])]
    #[Summary('Move an order to its next step')]
    #[Throws(
        exception: NotFoundException::class,
        status: 404
    )]
    #[Throws(
        exception: ValidationException::class,
        status: 422,
        description: 'Not the next step of this order'
    )]
    public function advance(string $code, #[Body] StatusChange $change): array
    {
        $order = $this->orders->byCode($code) ?? throw new NotFoundException("No order $code");
        if ($order->status->next() !== OrderStatus::from($change->status) || !$this->orders->advance($order->id, $order->status)) {
            throw new ValidationException(errors: ['status' => "The order is {$order->status->value}; its next step is " . ($order->status->next()->value ?? 'none') . '.']);
        }
        $this->feed->announce($order->id);
        return self::json($this->orders->byId($order->id) ?? $order);
    }

    /** @return array<string, mixed> */
    private static function json(Order $order): array
    {
        return [
            'code' => $order->code,
            'customer' => $order->customerName,
            'pickupDate' => $order->pickupDate,
            'status' => $order->status->value,
            'totalCents' => $order->totalCents,
            'lines' => $order->lines,
        ];
    }
}

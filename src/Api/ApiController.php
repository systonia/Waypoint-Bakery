<?php

namespace Bakery\Api;

use Bakery\Backoffice\Users;
use Bakery\Catalog\Products;
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

    #[Post('/tokens')]
    #[Tags(['Sign-in'])]
    #[Summary('Exchange email and password for a bearer token')]
    #[Throws(
        exception: UnauthorizedException::class,
        status: 401,
        description: 'Wrong email or password'
    )]
    public function token(#[Body] Credentials $credentials): BearerToken
    {
        $user = $this->users->verify($credentials->email, $credentials->password)
            ?? throw new UnauthorizedException('Wrong email or password.');
        return new BearerToken(JWT::encode($user->claims()));
    }

    /** @return list<ProductSummary> */
    #[Get('/products')]
    #[Tags(['Products'])]
    #[Summary('The range with prices in cents and what is left')]
    public function products(): array
    {
        return array_map(ProductSummary::of(...), $this->products->all());
    }

    /** @return list<OrderSummary> */
    #[Get('/orders')]
    #[Authenticated]
    #[Tags(['Orders'])]
    #[Summary('Every order that is not picked up or cancelled')]
    public function openOrders(): array
    {
        return array_map(OrderSummary::of(...), $this->orders->open());
    }

    #[Get('/orders/{code}')]
    #[Authenticated]
    #[Tags(['Orders'])]
    #[Summary('One order')]
    #[Throws(
        exception: NotFoundException::class,
        status: 404
    )]
    public function order(string $code): OrderSummary
    {
        return OrderSummary::of($this->find($code));
    }

    /** Moves the order one step on; the customer's page and every order board show it at once. */
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
    public function advance(string $code, #[Body] StatusChange $change): OrderSummary
    {
        $order = $this->find($code);
        if ($order->status->next() !== OrderStatus::from($change->status) || !$this->orders->advance($order->id, $order->status)) {
            throw new ValidationException(errors: ['status' => "The order is {$order->status->value}; its next step is " . ($order->status->next()->value ?? 'none') . '.']);
        }
        $this->feed->announce($order->id);
        return OrderSummary::of($this->find($code));
    }

    private function find(string $code): Order
    {
        return $this->orders->byCode($code) ?? throw new NotFoundException("No order $code");
    }
}

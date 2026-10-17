<?php

namespace Bakery\Api;

use Bakery\Backoffice\Users;
use Bakery\Catalog\{Product, Products};
use Bakery\Ordering\{Order, Orders};
use Waypoint\Attributes\{Authenticated, Body, Controller, Get, Inject, Patch, Post, Returns, SkipCsrf, Summary, Tags, Throws, Version};
use Waypoint\Exceptions\{NotFoundException, UnauthorizedException, ValidationException};
use Waypoint\JWT;
use Waypoint\Live\Broadcast;

/**
 * The JSON API, for the till and the delivery app. Programs send a bearer token, not cookies, so CSRF does not
 * apply. The OpenAPI document is generated from these attributes and the return types: /openapi/swagger.html.
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
    private Broadcast $broadcast;

    #[Post('/tokens')]
    #[Tags(['Sign-in'])]
    #[Summary('Exchange email and password for a bearer token')]
    #[Throws(
        exception: UnauthorizedException::class,
        description: 'Wrong email or password'
    )]
    public function token(#[Body] Credentials $credentials): BearerToken
    {
        $user = $this->users->verify($credentials->email, $credentials->password)
            ?? throw new UnauthorizedException('Wrong email or password.');
        return new BearerToken(JWT::encode($user));
    }

    /** @return list<Product> */
    #[Get('/products')]
    #[Tags(['Products'])]
    #[Summary('The range with prices in cents and what is left')]
    #[Returns(
        type: Product::class,
        list: true
    )]
    public function products(): array
    {
        return $this->products->all();
    }

    /** @return list<OrderSummary> */
    #[Get('/orders')]
    #[Authenticated]
    #[Tags(['Orders'])]
    #[Summary('Every order that is not picked up or cancelled')]
    #[Returns(
        type: OrderSummary::class,
        list: true
    )]
    public function openOrders(): array
    {
        return array_map(OrderSummary::of(...), $this->orders->open());
    }

    #[Get('/orders/{code}')]
    #[Authenticated]
    #[Tags(['Orders'])]
    #[Summary('One order')]
    #[Throws(NotFoundException::class)]
    public function order(string $code): OrderSummary
    {
        return OrderSummary::of($this->find($code));
    }

    /** Moves the order one step on; the customer's page and every order board show it at once. */
    #[Patch('/orders/{code}')]
    #[Authenticated]
    #[Tags(['Orders'])]
    #[Summary('Move an order to its next step')]
    #[Throws(NotFoundException::class)]
    public function advance(string $code, #[Body] StatusChange $change): OrderSummary
    {
        $order = $this->find($code);
        if ($order->status->next() !== $change->status || !$this->orders->advance($order->id, $order->status)) {
            $now = $this->find($code)->status;   // read again: another click may have moved it in the meantime
            throw new ValidationException(errors: ['status' => "The order is {$now->value}; its next step is " . ($now->next()->value ?? 'none') . '.']);
        }
        $this->broadcast->changed();
        return OrderSummary::of($this->find($code));
    }

    private function find(string $code): Order
    {
        return $this->orders->byCode($code) ?? throw new NotFoundException("No order $code");
    }
}

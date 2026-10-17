<?php

namespace Bakery\Tests\Controllers;

use Bakery\Tests\Support\AppTestCase;

final class SessionControllerTest extends AppTestCase
{
    public function testStaffSignInWithTheFormAndAreWelcomedWithAFlashMessage(): void
    {
        $this->request('GET', '/backoffice')->asNavigation()->send()->assertRedirect('/backoffice/login');

        $this->request('POST', '/backoffice/login')->withBody(['email' => 'ben@sonnenkorn.test', 'password' => 'baguette'])->withCsrf()->send()
            ->assertStatus(422)
            ->assertSee('Wrong email or password.');

        $login = $this->request('POST', '/backoffice/login')->withBody(['email' => 'ben@sonnenkorn.test', 'password' => 'pretzel'])->withCsrf()->send()
            ->assertRedirect('/backoffice', 303);
        $this->request('GET', '/backoffice')
            ->withCookie('session', $login->cookie('session') ?? '')
            ->withCookie('flash', $login->cookie('flash') ?? '')
            ->send()
            ->assertOk()
            ->assertSee('Welcome, Ben.')
            ->assertSee('Sign out Ben')
            ->assertDontSee('/backoffice/sales')
            ->assertSee('<app-order-board data-live');
    }
}

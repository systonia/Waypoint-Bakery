<?php

namespace Bakery\Tests\Components;

use Bakery\Tests\Support\AppTestCase;
use Waypoint\I18n\Translator;

final class CheckoutTest extends AppTestCase
{
    public function testTheFormIsValidatedInTheCustomersLanguage(): void
    {
        $this->app()->get(Translator::class)->setLocale('de');
        $tab = $this->customerTab();
        $tab->mount('app-add-to-cart', ['product' => '4'])->click('add');
        $tab->mount('app-checkout')
            ->submit('form', ['name' => '', 'email' => 'nope', 'pickupDate' => date('Y-m-d')])
            ->assertSee('Dieser Wert darf nicht leer sein.')
            ->assertSee('Dieser Wert ist keine gültige E-Mail-Adresse.')
            ->assertSee('Bitte wähle einen der Abholtage.');
        $this->assertSame(0, $this->sql("SELECT count(*) FROM orders WHERE status = 'pending'"));
    }

    public function testPlacingAnOrderReservesTheStockEmptiesTheCartAndLeadsToTheThanksPage(): void
    {
        $tab = $this->customerTab();
        $tab->mount('app-add-to-cart', ['product' => '4'])->click('add')->click('add');
        $tab->mount('app-checkout')->submit('form', ['name' => 'Mia', 'email' => 'mia@example.test', 'pickupDate' => $this->pickupDay()]);

        $code = $this->sql("SELECT code FROM orders WHERE email = 'mia@example.test'");
        $this->assertIsString($code);
        $tab->assertNavigatedTo("/orders/$code/thanks");
        $this->assertSame(34, $this->sql('SELECT stock FROM products WHERE id = 4'));
        $this->assertSame(0, $this->sql('SELECT count(*) FROM cart_lines'));
    }
}

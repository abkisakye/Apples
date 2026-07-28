<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_uses_wholesale_friendly_positioning(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSeeText('Wholesale & Retail Management System', false)
            ->assertSee('carton and piece stock control')
            ->assertSee('Wholesale-ready stock')
            ->assertSee('cartons, sacks, boxes, dozens, pieces, kg');
    }
}

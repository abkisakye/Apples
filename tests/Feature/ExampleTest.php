<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAsRole('admin');
    }

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response
            ->assertStatus(200)
            ->assertSee('Business Dashboard');
    }

    public function test_core_listing_pages_are_available(): void
    {
        $this->get('/customers')->assertOk();
        $this->get('/suppliers')->assertOk();
        $this->get('/products')->assertOk();
        $this->get('/users')->assertOk()->assertSee('Source')->assertSee('Access Department');
        $this->get('/roles')->assertOk()->assertSee('Roles');
        $this->get('/permissions')->assertOk()->assertSee('Permissions Matrix');
        $this->get('/admin/uat-center')->assertOk()->assertSee('UAT Center')->assertSee('Cashier UAT');
        $this->get('/admin/demo-center')->assertOk()->assertSee('Demo Center');
        $this->get('/admin/readiness')->assertOk()->assertSee('Production Readiness');
        $this->get('/follow-ups')->assertOk();
        $this->get('/reports/customer-aging')->assertOk();
        $this->get('/reports/supplier-aging')->assertOk();
        $this->get('/stock/balances')->assertOk();
        $this->get('/stock/reorder')->assertOk();
        $this->get('/stock/transfers')->assertOk();
        $this->get('/stock/adjustments')->assertOk();
        $this->get('/stock/transfers/create')->assertOk();
        $this->get('/stock/adjustments/create')->assertOk();
        $this->get('/sales')->assertOk();
        $this->get('/sales/create')->assertOk();
        $this->get('/purchases')->assertOk();
        $this->get('/purchases/create')->assertOk();
        $this->get('/capital-inputs')->assertOk();
        $this->get('/capital-inputs/create')->assertOk();
        $this->get('/settings/business')->assertOk()->assertSee('Business Settings');
        $this->get('/customer-payments/create')->assertOk();
        $this->get('/supplier-payments/create')->assertOk();
    }

    public function test_readiness_page_and_command_show_hardening_checks(): void
    {
        $this->get('/admin/readiness')
            ->assertOk()
            ->assertSee('App Key')
            ->assertSee('Backup Folder')
            ->assertSee('Operational Runbook');

        $this->artisan('ops:go-live-check')
            ->expectsOutputToContain('Application - App Key')
            ->expectsOutputToContain('Operations - Backup Folder')
            ->assertExitCode(1);
    }
}

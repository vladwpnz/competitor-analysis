<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_is_available(): void
    {
        $this->get(route('home'))
            ->assertOk();
    }

    public function test_home_page_explains_reference_led_account_discovery(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Find more companies')
            ->assertSee('like your best customers.')
            ->assertSee('Reference company website')
            ->assertSee('Reference Google Business Profile')
            ->assertSee('Find lookalike accounts')
            ->assertSee('Ranking account fit')
            ->assertDontSee('Competitor Intelligence');
    }

    public function test_public_account_routes_do_not_expose_competitor_urls(): void
    {
        $this->assertSame(
            url('/accounts'),
            route('accounts')
        );

        $this->assertSame(
            url('/accounts/search'),
            route('accounts.search')
        );

        $this->assertSame(
            url('/accounts/add'),
            route('accounts.add')
        );

        $this->assertSame(
            url('/accounts/remove'),
            route('accounts.remove')
        );

        $this->get('/competitors')
            ->assertNotFound();
    }

    public function test_obsolete_preview_route_is_not_exposed(): void
    {
        $this->get('/preview')
            ->assertNotFound();
    }
}

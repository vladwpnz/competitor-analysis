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

    public function test_obsolete_preview_route_is_not_exposed(): void
    {
        $this->get('/preview')
            ->assertNotFound();
    }
}

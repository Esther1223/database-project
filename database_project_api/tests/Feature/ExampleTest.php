<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_homepage_returns_api_status(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertJson([
                'message' => 'Database Project API',
            ]);
    }
}

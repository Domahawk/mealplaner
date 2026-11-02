<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_laravel_serves_the_spa_entry_for_client_routes(): void
    {
        foreach (['/', '/about', '/nested/client/route'] as $path) {
            $this->get($path)->assertOk()->assertSee('<div id="app"></div>', false);
        }
    }

    public function test_unknown_api_routes_are_json_and_never_the_spa(): void
    {
        foreach (['/api', '/api/missing', '/api/health/missing'] as $path) {
            $response = $this->get($path)->assertNotFound();
            $response->assertHeader('Content-Type', 'application/json');
            $response->assertDontSee('<div id="app"></div>', false);
        }
    }

    public function test_static_and_framework_paths_are_not_caught_by_the_spa(): void
    {
        foreach (['/build/missing.js', '/missing.css', '/storage/missing', '/icons/missing.png', '/sanctum/missing', '/_ignition/missing', '/up/missing'] as $path) {
            $this->get($path)->assertNotFound()->assertDontSee('<div id="app"></div>', false);
        }
    }

    public function test_post_requests_do_not_return_the_spa(): void
    {
        $this->postJson('/about')->assertStatus(405);
    }
}

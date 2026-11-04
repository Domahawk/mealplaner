<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
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
        foreach (['/build/missing.js', '/missing.css', '/icons/missing.png', '/sanctum/missing', '/_ignition/missing', '/up/missing'] as $path) {
            $this->get($path)->assertNotFound()->assertDontSee('<div id="app"></div>', false);
        }
    }

    public function test_private_storage_paths_require_a_signed_url_and_never_return_the_spa(): void
    {
        $this->get('/storage/missing')
            ->assertForbidden()
            ->assertDontSee('<div id="app"></div>', false);
    }

    public function test_post_requests_do_not_return_the_spa(): void
    {
        $this->postJson('/about')->assertStatus(405);
    }

    public function test_unsigned_private_uploads_are_rejected_without_writing_a_file(): void
    {
        Storage::fake('local');

        $this->putJson('/storage/audit-rejected-upload.txt', ['value' => 'audit-only-fixture'])
            ->assertForbidden();

        Storage::disk('local')->assertMissing('audit-rejected-upload.txt');
    }
}

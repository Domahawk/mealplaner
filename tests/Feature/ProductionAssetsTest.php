<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionAssetsTest extends TestCase
{
    public function test_production_entry_references_built_assets(): void
    {
        if (! is_file(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Run npm run build before the production asset test.');
        }

        $this->app->make(\Illuminate\Foundation\Vite::class)->useHotFile(storage_path('framework/testing-no-hot-file'));

        $this->get('/about')->assertOk()->assertSee('/build/assets/', false);
        $this->get('/sw.js')->assertOk()
            ->assertHeader('Content-Type', 'application/javascript')
            ->assertHeader('Service-Worker-Allowed', '/')
            ->assertSee('offline.html');
    }
}

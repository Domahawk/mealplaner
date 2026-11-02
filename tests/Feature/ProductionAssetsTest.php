<?php

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class ProductionAssetsTest extends TestCase
{
    public function test_production_entry_references_built_assets(): void
    {
        if (! is_file(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Run npm run build before the production asset test.');
        }

        $this->app->make(Vite::class)->useHotFile(storage_path('framework/testing-no-hot-file'));

        $this->get('/about')->assertOk()->assertSee('/build/assets/', false);
        $response = $this->get('/sw.js')->assertOk()
            ->assertHeader('Content-Type', 'application/javascript')
            ->assertHeader('Service-Worker-Allowed', '/');

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $file = $response->baseResponse->getFile();
        $this->assertSame(realpath(public_path('build/sw.js')), $file->getRealPath());
        $this->assertStringContainsString('offline.html', $file->getContent());
    }
}

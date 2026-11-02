<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class ApplicationKeyTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/starter-key-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/scripts', 0700, true);
        copy(base_path('scripts/ensure-app-key.php'), $this->directory.'/scripts/ensure-app-key.php');
        symlink(base_path('vendor'), $this->directory.'/vendor');
    }

    protected function tearDown(): void
    {
        unlink($this->directory.'/.env');
        unlink($this->directory.'/scripts/ensure-app-key.php');
        unlink($this->directory.'/vendor');
        rmdir($this->directory.'/scripts');
        rmdir($this->directory);

        parent::tearDown();
    }

    public function test_empty_key_is_created_once_and_retained_on_restart(): void
    {
        file_put_contents($this->directory.'/.env', "APP_NAME=Test\nAPP_KEY=\n");
        $this->initializeKey();
        $initialized = file_get_contents($this->directory.'/.env');
        $this->assertIsString($initialized);
        $this->assertMatchesRegularExpression('/APP_KEY=base64:[A-Za-z0-9+\/=]{44}/', $initialized);

        $this->initializeKey();
        $this->assertSame($initialized, file_get_contents($this->directory.'/.env'));
    }

    public function test_an_existing_quoted_key_is_never_replaced(): void
    {
        $contents = "APP_NAME=Test\nAPP_KEY=\"base64:existing-value\"\n";
        file_put_contents($this->directory.'/.env', $contents);
        $this->initializeKey();
        $this->assertSame($contents, file_get_contents($this->directory.'/.env'));
    }

    private function initializeKey(): void
    {
        $process = new Process([PHP_BINARY, $this->directory.'/scripts/ensure-app-key.php']);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}

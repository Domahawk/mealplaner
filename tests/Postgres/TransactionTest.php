<?php

namespace Tests\Postgres;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use DatabaseTransactions;

    #[DataProvider('values')]
    public function test_records_are_rolled_back_between_tests(string $value): void
    {
        $key = 'transaction-isolation-probe';
        $this->assertSame(1, DB::connection()->transactionLevel());
        $this->assertDatabaseMissing('cache', ['key' => $key]);

        DB::table('cache')->insert([
            'key' => $key,
            'value' => $value,
            'expiration' => 2147483647,
        ]);

        $this->assertDatabaseHas('cache', ['key' => $key, 'value' => $value]);
        // Both datasets use the same key: the next case must observe the rollback.
    }

    /** @return array<string, array{string}> */
    public static function values(): array
    {
        return ['first record' => ['first'], 'second record' => ['second']];
    }
}

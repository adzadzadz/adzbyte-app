<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class MySqlVerificationSafetyTest extends TestCase
{
    #[DataProvider('unsafeTargets')]
    public function test_mysql_bootstrap_rejects_missing_or_non_test_targets(string|false $database, string $host): void
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/Support/mysql-bootstrap.php'], env: [
            'TEST_MYSQL_DATABASE' => $database, 'TEST_MYSQL_HOST' => $host,
        ]);
        $process->run();
        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('requires an explicit adzbyte_test_ database', $process->getErrorOutput());
    }

    public static function unsafeTargets(): array
    {
        return [[false, '127.0.0.1'], ['production', '127.0.0.1'], ['adzbyte_test_ci', 'production.example.test']];
    }
}

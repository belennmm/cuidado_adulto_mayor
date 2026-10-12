<?php

namespace Tests\Unit;

use App\Logging\AuditCollectorHandler;
use InvalidArgumentException;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuditCollectorHandlerTest extends TestCase
{
    #[DataProvider('invalidEndpoints')]
    public function test_unsafe_collector_endpoints_are_rejected(string $endpoint): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuditCollectorHandler($endpoint);
    }

    public function test_tls_endpoint_is_accepted_without_connecting(): void
    {
        $handler = new AuditCollectorHandler('tls://audit.example.test:6514');

        $this->assertSame('tls://audit.example.test:6514', $handler->getConnectionString());
    }

    public function test_collector_failure_keeps_the_local_copy_and_reports_a_safe_notice(): void
    {
        $collector = new class('tls://audit.example.test:6514') extends AuditCollectorHandler
        {
            public bool $failureReported = false;

            protected function fsockopen()
            {
                return false;
            }

            protected function reportFailure(): void
            {
                $this->failureReported = true;
            }
        };
        $local = new TestHandler();
        $logger = new Logger('security_audit', [$local, $collector]);
        $logger->addRecord(Level::Info, 'security.event', ['result' => 'success']);

        $this->assertCount(1, $local->getRecords());
        $this->assertTrue($collector->failureReported);
    }

    public static function invalidEndpoints(): array
    {
        return [
            ['tcp://audit.example.test:6514'], ['http://audit.example.test:6514'],
            ['tls://user:secret@audit.example.test:6514'], ['tls://audit.example.test:6514/path'],
            ['tls://audit.example.test'], ['tls://audit.example.test:6514?token=secret'],
        ];
    }
}

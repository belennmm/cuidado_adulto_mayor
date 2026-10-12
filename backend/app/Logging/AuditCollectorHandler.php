<?php

namespace App\Logging;

use InvalidArgumentException;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\SocketHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class AuditCollectorHandler extends SocketHandler
{
    private readonly string $peerName;

    public function __construct(string $endpoint)
    {
        $parts = parse_url($endpoint);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'tls'
            || empty($parts['host']) || empty($parts['port'])
            || $parts['port'] < 1 || $parts['port'] > 65535
            || array_intersect(['user', 'pass', 'path', 'query', 'fragment'], array_keys($parts)) !== []) {
            throw new InvalidArgumentException('SECURITY_AUDIT_COLLECTOR_URL debe usar tls://host:puerto sin credenciales ni ruta.');
        }
        $this->peerName = trim($parts['host'], '[]');
        parent::__construct($endpoint, Level::Info, timeout: 2, writingTimeout: 2, connectionTimeout: 2);
        $this->setFormatter(new JsonFormatter());
    }

    protected function fsockopen()
    {
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
            'peer_name' => $this->peerName, 'SNI_enabled' => true,
        ]]);

        return @stream_socket_client($this->getConnectionString(), $errorCode, $errorMessage, 2, STREAM_CLIENT_CONNECT, $context);
    }

    protected function write(LogRecord $record): void
    {
        try {
            parent::write($record);
        } catch (Throwable) {
            $this->close();
            $this->reportFailure();
        }
    }

    protected function reportFailure(): void
    {
        error_log('security_audit_collector_unavailable: copia firmada conservada localmente.');
    }
}

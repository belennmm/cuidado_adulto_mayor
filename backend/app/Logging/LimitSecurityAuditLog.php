<?php

namespace App\Logging;

use DateTimeImmutable;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\HandlerWrapper;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use Throwable;

final class LimitSecurityAuditLog extends HandlerWrapper
{
    private static ?string $lastFailureMinute = null;

    public function __construct(
        HandlerInterface $local,
        private readonly ?HandlerInterface $collector,
        private readonly string $basePath,
        private readonly array $limits,
    ) {
        parent::__construct($local);
    }

    public function getUrl(): string
    {
        $parts = pathinfo($this->basePath);

        return $parts['dirname'].'/'.$parts['filename'].'-'.(new DateTimeImmutable())->format('Y-m-d')
            .(isset($parts['extension']) ? '.'.$parts['extension'] : '');
    }

    public function handle(LogRecord $record): bool
    {
        if (! $this->isHandling($record)) {
            return false;
        }
        $lock = null;
        $accepted = false;
        $notice = false;
        $minute = (new DateTimeImmutable())->format('Y-m-d-H-i');
        try {
            $bytes = strlen((new JsonFormatter())->format($record));
            $directory = dirname($this->basePath);
            if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Audit directory unavailable.');
            }
            $lockPath = $this->basePath.'.budget.lock';
            if (is_link($lockPath) || is_link($this->getUrl())) {
                throw new RuntimeException('Audit path unavailable.');
            }
            $lock = @fopen($lockPath, 'c+b');
            if ($lock === false || ! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Audit budget unavailable.');
            }
            @chmod($lockPath, 0600);
            $raw = stream_get_contents($lock, 1024);
            $state = $raw === '' ? [] : json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
            if (! is_array($state)) {
                throw new RuntimeException('Audit budget invalid.');
            }
            if (($state['minute'] ?? null) !== $minute) {
                $state = ['minute' => $minute, 'normal' => 0, 'urgent' => 0, 'notice' => false];
            }
            foreach (['normal', 'urgent'] as $counter) {
                if (! is_int($state[$counter] ?? null) || $state[$counter] < 0) {
                    throw new RuntimeException('Audit budget invalid.');
                }
            }
            $urgent = $record->level->value >= Level::Error->value;
            $bucket = $urgent ? 'urgent' : 'normal';
            $quota = $this->limits[$urgent ? 'urgent_per_minute' : 'events_per_minute'];
            $daily = $urgent ? $this->limits['daily_bytes'] : (int) floor($this->limits['daily_bytes'] * 0.9);
            $path = $this->getUrl();
            clearstatcache(true, $path);
            $size = is_file($path) ? filesize($path) : 0;
            if ($size === false) {
                throw new RuntimeException('Audit size unavailable.');
            }
            $accepted = $bytes <= $this->limits['record_bytes'] && $state[$bucket] < $quota && $size + $bytes <= $daily;
            if ($accepted) {
                $state[$bucket]++;
            } elseif (! ($state['notice'] ?? false)) {
                $state['notice'] = true;
                $notice = true;
            }
            $encoded = json_encode($state, JSON_THROW_ON_ERROR);
            if (! rewind($lock) || ! ftruncate($lock, 0) || fwrite($lock, $encoded) !== strlen($encoded) || ! fflush($lock)) {
                throw new RuntimeException('Audit budget write failed.');
            }
            if ($accepted) {
                $this->handler->handle(clone $record);
            }
        } catch (Throwable) {
            $accepted = false;
            if (self::$lastFailureMinute !== $minute) {
                self::$lastFailureMinute = $minute;
                error_log('security_audit_budget_unavailable');
            }
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        if ($notice) {
            error_log('security_audit_capacity_limited');
        }
        if ($accepted && $this->collector !== null) {
            $this->collector->handle(clone $record);
        }

        return false;
    }

    public function handleBatch(array $records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    public function close(): void
    {
        parent::close();
        $this->collector?->close();
    }
}

<?php

namespace App\Services;

use DateTimeImmutable;
use DirectoryIterator;
use InvalidArgumentException;
use RuntimeException;

class AuditLogRetention
{
    public function days(): int
    {
        $days = filter_var(config('security_audit.retention_days'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 365],
        ]);
        if ($days === false) {
            throw new InvalidArgumentException('SECURITY_AUDIT_RETENTION_DAYS debe ser un entero entre 1 y 365.');
        }

        return $days;
    }

    public function prune(bool $dryRun = false): int
    {
        $cutoff = today()->subDays($this->days() - 1)->format('Y-m-d');
        $path = config('logging.channels.security_audit.path');
        $directory = dirname($path);
        if (! is_dir($directory)) {
            return 0;
        }

        $parts = pathinfo($path);
        $pattern = '/^'.preg_quote($parts['filename'], '/').'-(\d{4}-\d{2}-\d{2})'
            .preg_quote(isset($parts['extension']) ? '.'.$parts['extension'] : '', '/').'$/D';
        $count = 0;
        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isLink() || ! $file->isFile() || ! preg_match($pattern, $file->getFilename(), $matches)) {
                continue;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $matches[1]);
            if ($date === false || $date->format('Y-m-d') !== $matches[1] || $matches[1] >= $cutoff) {
                continue;
            }
            if (! $dryRun && ! unlink($file->getPathname())) {
                throw new RuntimeException('No se pudo eliminar un archivo de auditoria vencido.');
            }
            $count++;
        }

        return $count;
    }
}

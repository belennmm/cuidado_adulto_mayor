<?php

use Illuminate\Foundation\Inspiring;
use App\Support\AuditRecordIntegrity;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('security:audit-verify {path?}', function (AuditRecordIntegrity $integrity) {
    $path = $this->argument('path') ?: config('logging.channels.security_audit.path');
    if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
        $this->error('Archivo de auditoria no disponible.');

        return 1;
    }

    $total = 0;
    $invalid = 0;
    foreach (new SplFileObject($path, 'r') as $line) {
        if (trim($line) === '') {
            continue;
        }
        $total++;
        try {
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $valid = is_array($record) && $integrity->verify($record);
        } catch (JsonException) {
            $valid = false;
        }
        if (! $valid) {
            $invalid++;
        }
    }

    $this->info("Registros verificados: {$total}. Invalidos: {$invalid}.");

    return $invalid === 0 ? 0 : 1;
})->purpose('Verificar firmas de eventos de auditoria sin mostrar su contenido');

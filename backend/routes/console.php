<?php

use Illuminate\Foundation\Inspiring;
use App\Support\AuditRecordIntegrity;
use App\Services\AuditLogRetention;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('security:audit-verify {path?}', function (AuditRecordIntegrity $integrity) {
    $path = $this->argument('path') ?: Log::channel('security_audit')->getLogger()->getHandlers()[0]->getUrl();
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

Artisan::command('security:audit-prune {--dry-run}', function (AuditLogRetention $retention) {
    $dryRun = (bool) $this->option('dry-run');
    $count = $retention->prune($dryRun);
    $this->info(($dryRun ? 'Archivos vencidos encontrados: ' : 'Archivos vencidos eliminados: ').$count.'.');

    return 0;
})->purpose('Limpiar exclusivamente archivos diarios de auditoria vencidos');

Schedule::command('security:audit-prune')->dailyAt('02:00')->withoutOverlapping();

Artisan::command('security:audit-health', function (App\Services\SecurityAuditHealthCheck $health) {
    if (! $health->verify()) {
        $this->error('Auditoria no disponible o evento de control no verificable.');

        return 1;
    }
    $this->info('Generacion, almacenamiento y firma de auditoria verificados.');

    return 0;
})->purpose('Escribir y verificar un evento de control de auditoria');

Schedule::command('security:audit-health')->everyFiveMinutes()->withoutOverlapping();

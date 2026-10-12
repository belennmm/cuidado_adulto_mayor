<?php

namespace App\Providers;

use App\Logging\AuditCollectorHandler;
use App\Services\AuditLogRetention;
use App\Support\AuditRecordIntegrity;
use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\MobilityExercise;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\PersonalAccessToken;
use App\Models\RoutineNote;
use App\Models\Rutina;
use App\Models\User;
use App\Models\VacationRequest;
use App\Observers\UserObserver;
use App\Policies\CaregiverSchedulePolicy;
use App\Policies\IncidentPolicy;
use App\Policies\MobilityExercisePolicy;
use App\Policies\OlderAdultMedicationPolicy;
use App\Policies\OlderAdultPolicy;
use App\Policies\RoutineNotePolicy;
use App\Policies\RutinaPolicy;
use App\Policies\UserPolicy;
use App\Policies\VacationRequestPolicy;
use App\Support\RuntimeDatabaseAccount;
use App\Support\ResourceAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditRecordIntegrity::class, fn () => new AuditRecordIntegrity(
            (string) (config('security_audit.integrity_key') ?: ($this->app->environment('production') ? '' : config('app.key'))),
            (string) config('security_audit.key_id', 'v1'),
        ));
    }

    public function boot(): void
    {
        $this->validateProductionConfiguration();
        config(['logging.channels.security_audit.days' => $this->app->make(AuditLogRetention::class)->days()]);
        $this->app->make(AuditRecordIntegrity::class);
        $collector = config('security_audit.collector_url');
        if (is_string($collector) && $collector !== '') {
            new AuditCollectorHandler($collector);
        }
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        User::observe(UserObserver::class);

        Gate::policy(Rutina::class, RutinaPolicy::class);
        Gate::policy(OlderAdult::class, OlderAdultPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Incident::class, IncidentPolicy::class);
        Gate::policy(RoutineNote::class, RoutineNotePolicy::class);
        Gate::policy(CaregiverSchedule::class, CaregiverSchedulePolicy::class);
        Gate::policy(OlderAdultMedication::class, OlderAdultMedicationPolicy::class);
        Gate::policy(MobilityExercise::class, MobilityExercisePolicy::class);
        Gate::policy(VacationRequest::class, VacationRequestPolicy::class);

        RateLimiter::for('login', fn (Request $request) => [
            // The service separately limits failed attempts for each email/IP pair.
            Limit::perMinute(config('abuse.login_per_minute'))->by($request->ip()),
        ]);
        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perHour(10)->by($request->ip()),
        ]);
        RateLimiter::for('sensitive-api', function (Request $request) {
            if ($request->route()?->getActionMethod() === 'logout') {
                return Limit::none();
            }

            $account = (string) $request->user()->id;
            if (in_array($request->method(), ['GET', 'HEAD'], true)) {
                return Limit::perMinute(config('abuse.reads_per_minute'))->by('read:'.$account);
            }

            $limits = [
                Limit::perMinute(config('abuse.writes_per_minute'))->by('write:'.$account),
                Limit::perHour(config('abuse.writes_per_hour'))->by('write-hour:'.$account),
            ];
            $uri = $request->route()?->uri() ?? '';
            $administrative = str_starts_with($uri, 'api/admin/');
            $critical = $administrative
                ? ResourceAccess::admin($request->user()) && ($request->method() === 'DELETE'
                    || str_starts_with($uri, 'api/admin/users') || $request->route()?->getActionMethod() === 'adjustStock')
                : $request->method() === 'DELETE' && (ResourceAccess::admin($request->user()) || ResourceAccess::caregiver($request->user()));
            if ($critical) {
                $limits[] = Limit::perMinute(config('abuse.critical_per_minute'))->by('critical:'.$account);
            }

            return $limits;
        });
    }

    private function validateProductionConfiguration(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $required = [
            'APP_KEY' => config('app.key'),
            'APP_URL' => config('app.url'),
            'CORS_ALLOWED_ORIGINS' => config('cors.allowed_origins'),
        ];
        $missing = array_keys(array_filter($required, fn (mixed $value) => $value === null || $value === '' || $value === []));
        $missing = array_merge($missing, RuntimeDatabaseAccount::errors(
            config('database.connections.'.config('database.default'), []),
        ));

        if (config('app.debug')) {
            $missing[] = 'APP_DEBUG debe ser false';
        }

        $origins = config('cors.allowed_origins', []);
        if (array_filter($origins, fn (string $origin) => ! str_starts_with($origin, 'https://'))) {
            $missing[] = 'CORS_ALLOWED_ORIGINS debe usar HTTPS';
        }

        if ($missing !== []) {
            throw new RuntimeException('Configuracion de produccion insegura: '.implode(', ', $missing));
        }
    }
}

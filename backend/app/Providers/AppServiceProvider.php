<?php

namespace App\Providers;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\MobilityExercise;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->validateProductionConfiguration();
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
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perHour(10)->by($request->ip()),
        ]);
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

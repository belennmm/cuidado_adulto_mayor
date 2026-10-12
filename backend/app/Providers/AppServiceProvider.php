<?php

namespace App\Providers;

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
use App\Support\ResourceAccess;
use App\Support\RuntimeDatabaseAccount;
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
        //
    }

    public function boot(): void
    {
        $this->validateProductionConfiguration();
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
        RateLimiter::for('reauthentication', fn (Request $request) => [
            Limit::perMinute(5)->by('account:'.$request->user()->id),
            Limit::perMinute(10)->by('ip:'.$request->ip()),
        ]);
        RateLimiter::for('recovery', fn (Request $request) => [
            Limit::perHour(3)->by('account:'.hash('sha256', mb_strtolower(trim(is_string($request->input('email')) ? $request->input('email') : '')))),
            Limit::perHour(10)->by('ip:'.$request->ip()),
        ]);
        RateLimiter::for('reset-password', fn (Request $request) => [
            Limit::perMinute(5)->by('account:'.hash('sha256', mb_strtolower(trim(is_string($request->input('email')) ? $request->input('email') : '')))),
            Limit::perMinute(10)->by('ip:'.$request->ip()),
        ]);
        RateLimiter::for('mfa-verification', fn (Request $request) => [Limit::perMinute(10)->by('ip:'.$request->ip())]);
        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perHour(10)->by($request->ip()),
        ]);
        RateLimiter::for('sensitive-api', function (Request $request) {
            if (in_array($request->route()?->getActionMethod(), ['logout', 'logoutAll'], true)) {
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

        if (! config('session.secure') || ! config('session.http_only')) {
            $missing[] = 'SESSION_SECURE_COOKIE y SESSION_HTTP_ONLY deben ser true';
        }
        if (! in_array(config('session.same_site'), ['lax', 'strict', 'none'], true)) {
            $missing[] = 'SESSION_SAME_SITE no valido';
        }
        if ((int) config('session.idle_timeout') < 1 || (int) config('session.absolute_timeout') < 1) {
            $missing[] = 'Los tiempos de sesion deben ser positivos';
        }
        if (in_array(config('session.driver'), ['cookie', 'array', 'null'], true)) {
            $missing[] = 'SESSION_DRIVER debe persistir las sesiones en el servidor';
        }

        if ((int) config('session.max_per_user') < 1 || (int) config('session.reauthentication_timeout') < 1) {
            $missing[] = 'SESSION_MAX_PER_USER y SESSION_REAUTHENTICATION_TIMEOUT deben ser positivos';
        }
        if (! config('passwords.check_compromised')) {
            $missing[] = 'PASSWORD_CHECK_COMPROMISED debe ser true';
        }
        if (! filter_var(config('auth.password_reset_url'), FILTER_VALIDATE_URL) || ! str_starts_with(config('auth.password_reset_url'), 'https://')
            || parse_url(config('auth.password_reset_url'), PHP_URL_QUERY) || parse_url(config('auth.password_reset_url'), PHP_URL_FRAGMENT)) {
            $missing[] = 'PASSWORD_RESET_URL debe usar HTTPS sin query ni fragmento';
        }
        if (! $this->deliversPrivateMail((string) config('mail.default'))) {
            $missing[] = 'MAIL_MAILER debe entregar correos sin registrar tokens';
        }
        $origins = config('cors.allowed_origins', []);
        if (array_filter($origins, fn (string $origin) => ! str_starts_with($origin, 'https://'))) {
            $missing[] = 'CORS_ALLOWED_ORIGINS debe usar HTTPS';
        }

        if ($missing !== []) {
            throw new RuntimeException('Configuracion de produccion insegura: '.implode(', ', $missing));
        }
    }

    private function deliversPrivateMail(string $name, array $visited = []): bool
    {
        if (in_array($name, $visited, true)) {
            return false;
        }
        $mailer = config('mail.mailers.'.$name, []);
        $transport = $mailer['transport'] ?? '';
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $mailer['mailers'] ?? [];
            foreach ($children as $child) {
                if (! $this->deliversPrivateMail($child, [...$visited, $name])) {
                    return false;
                }
            }

            return $children !== [];
        }

        return in_array($transport, ['smtp', 'sendmail', 'mailgun', 'ses', 'ses-v2', 'postmark', 'resend'], true);
    }
}

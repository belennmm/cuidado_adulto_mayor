<?php

namespace App\Services;

use App\Support\SecurityEventSeverity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Throwable;

class SecurityAlertService
{
    public function settings(): array
    {
        $settings = [];
        foreach (['window_seconds', 'cooldown_seconds', 'brute_force', 'denials', 'mass_access'] as $name) {
            $value = filter_var(config('security_audit.alerts.'.$name), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 86400],
            ]);
            if ($value === false) {
                throw new InvalidArgumentException('Configuracion invalida de alertas de seguridad: '.$name);
            }
            $settings[$name] = $value;
        }

        return $settings;
    }

    public function observe(Request $request, array $context): void
    {
        $rule = match (true) {
            $context['event'] === 'authentication.login' && in_array($context['result'], ['denied', 'blocked'], true) => 'brute_force',
            in_array($context['event'], ['authorization.denied', 'authentication.required', 'resource.not_found'], true) => 'denials',
            $context['event'] === 'sensitive_data.access' && $context['result'] === 'success' => 'mass_access',
            $context['event'] === 'sensitive_data.large_response' => 'large_response',
            in_array($context['event'], ['user.role_changed', 'account.status_changed'], true) => 'account_change',
            default => null,
        };
        if ($rule === null || $request->attributes->get('security_alert_counted_'.$rule)) {
            return;
        }
        $request->attributes->set('security_alert_counted_'.$rule, true);
        $subject = $rule !== 'brute_force' && $context['actor_id'] !== null
            ? 'actor:'.$context['actor_id'] : 'ip:'.($request->ip() ?? '');
        if ($subject === 'ip:') {
            return;
        }
        $settings = $this->settings();
        $subjects = [$subject];
        $email = $request->input('email');
        if ($rule === 'brute_force' && is_string($email) && strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $subjects[] = 'account:'.mb_strtolower(trim($email));
        }

        try {
            $triggered = $rule === 'account_change';
            foreach ($rule === 'account_change' ? [] : $subjects as $identity) {
                $key = 'security-alert:'.$rule.':'.hash_hmac('sha256', $identity, (string) config('app.key'));
                $count = RateLimiter::hit($key, $settings['window_seconds']);
                if ($count >= ($settings[$rule] ?? 1) && Cache::add($key.':cooldown', true, $settings['cooldown_seconds'])) {
                    $triggered = true;
                }
            }
            if (! $triggered) {
                return;
            }
        } catch (Throwable) {
            error_log('security_alert_detection_unavailable');
            $context['event'] = 'security.alert.detection_unavailable';
            $context['action'] = 'detect';
            $context['result'] = 'failure';
            $context['resource_type'] = 'security_rule';
            $context['resource_id'] = null;
            Log::channel('security_audit')->log(
                SecurityEventSeverity::level($context['event'], $context['result']), 'security.alert', $context,
            );

            return;
        }

        $context['event'] = 'security.alert.'.$rule;
        $context['action'] = 'detect';
        $context['result'] = 'triggered';
        $context['resource_type'] = 'security_rule';
        $context['resource_id'] = null;
        Log::channel('security_audit')->log(
            SecurityEventSeverity::level($context['event'], $context['result']), 'security.alert', $context,
        );
    }
}

<?php

namespace App\Support;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Support\Str;

final class ResourceAccess
{
    public static function admin(User $user): bool
    {
        return $user->role === 'admin' && (bool) $user->is_approved;
    }

    public static function professional(User $user): bool
    {
        return $user->is_approved && in_array(self::role($user), ['profesional', 'cuidador_profesional'], true);
    }

    public static function family(User $user): bool
    {
        return $user->is_approved && in_array(self::role($user), ['familiar', 'cuidador_familiar'], true);
    }

    public static function caregiver(User $user): bool
    {
        return self::professional($user) || self::family($user);
    }

    public static function owns(User $user, mixed $ownerId): bool
    {
        return (int) $user->id > 0 && (int) $ownerId === (int) $user->id;
    }

    public static function professionalAssigned(User $user, ?OlderAdult $adult): bool
    {
        return $adult !== null && self::professional($user)
            && self::owns($user, $adult->professional_caregiver_id);
    }

    public static function familyAssigned(User $user, ?OlderAdult $adult): bool
    {
        if ($adult === null || ! self::family($user)) {
            return false;
        }

        return self::owns($user, $adult->family_caregiver_id);
    }

    public static function assigned(User $user, ?OlderAdult $adult): bool
    {
        return self::professionalAssigned($user, $adult) || self::familyAssigned($user, $adult);
    }

    private static function role(User $user): string
    {
        return Str::of((string) $user->role)->ascii()->lower()->trim()->toString();
    }
}

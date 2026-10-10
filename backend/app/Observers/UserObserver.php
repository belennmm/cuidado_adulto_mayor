<?php

namespace App\Observers;

use App\Models\User;
use App\Services\CredentialRevocationService;

class UserObserver
{
    public function __construct(private readonly CredentialRevocationService $credentials) {}

    public function updated(User $user): void
    {
        if ($user->wasChanged(['role', 'password'])
            || ($user->wasChanged('is_approved') && ! $user->is_approved)) {
            $this->credentials->revoke($user, $user->getOriginal('email'));
        }
    }

    public function deleting(User $user): void
    {
        $this->credentials->revoke($user);
    }
}

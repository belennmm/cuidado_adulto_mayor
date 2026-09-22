<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $action = $request->route()?->getActionMethod();
        $user = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_approved' => $this->is_approved,
        ];

        if (in_array($action, ['professionalCaregivers', 'familyCaregivers', 'approve'], true)) {
            return $user;
        }

        $user += [
            'location' => $this->location,
            'phone' => $this->phone,
            'birthdate' => $this->birthdate,
        ];

        if ($action === 'index') {
            $user['created_at'] = $this->created_at;
        }

        return $user;
    }
}

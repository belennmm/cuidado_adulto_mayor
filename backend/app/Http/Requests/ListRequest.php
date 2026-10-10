<?php

namespace App\Http\Requests;

use App\Support\ListPage;

class ListRequest extends StrictFormRequest
{
    public function rules(): array
    {
        $rules = ListPage::rules();
        if (in_array($this->route()?->uri(), [
            'api/admin/users', 'api/admin/older-adults', 'api/family/older-adults', 'api/professional/older-adults',
            'api/admin/professional-caregivers', 'api/admin/family-caregivers',
        ], true)) {
            $rules['q'] = ['sometimes', 'string', 'max:255'];
        }
        if ($this->route()?->uri() === 'api/admin/users') {
            $rules['is_approved'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}

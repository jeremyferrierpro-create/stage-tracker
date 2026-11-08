<?php

namespace App\Http\Requests\Organization;

use App\Domain\Organization\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string', Rule::in(Organization::TYPES)],
            'contact_email' => ['nullable', 'string', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

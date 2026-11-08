<?php

namespace App\Http\Requests\Identity;

use App\Domain\Identity\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:190'],
            'role' => ['required', 'string', Rule::in(Workspace::ROLES)],
        ];
    }
}

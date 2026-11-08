<?php

namespace App\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

class RegisterViaInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }
}

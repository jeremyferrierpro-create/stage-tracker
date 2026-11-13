<?php

namespace App\Http\Requests\Framework;

use Illuminate\Foundation\Http\FormRequest;

class CompetencyCriterionRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}

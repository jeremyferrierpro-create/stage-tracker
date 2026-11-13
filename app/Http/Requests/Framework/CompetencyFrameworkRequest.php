<?php

namespace App\Http\Requests\Framework;

use App\Domain\Framework\Models\CompetencyFramework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompetencyFrameworkRequest extends FormRequest
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
        /** @var CompetencyFramework|null $framework */
        $framework = $this->route('framework');

        return [
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('competency_frameworks', 'name')->ignore($framework?->getKey()),
            ],
            'issuing_authority' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

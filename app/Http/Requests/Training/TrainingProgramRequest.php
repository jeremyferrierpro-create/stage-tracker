<?php

namespace App\Http\Requests\Training;

use App\Domain\Identity\Models\Workspace;
use App\Domain\Organization\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrainingProgramRequest extends FormRequest
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
        /** @var Workspace|null $workspace */
        $workspace = $this->route('workspace');

        return [
            'organization_id' => [
                'required',
                'uuid',
                Rule::exists('organizations', 'id')->where('workspace_id', $workspace?->getKey()),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $organization = Organization::query()->find($value);

                    if ($organization !== null && $organization->isArchived()) {
                        $fail("L'organisation choisie est archivée.");
                    }
                },
            ],
            'name' => ['required', 'string', 'max:160'],
            'certification' => ['nullable', 'string', 'max:160'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}

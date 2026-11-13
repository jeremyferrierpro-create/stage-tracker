<?php

namespace App\Http\Requests\Framework;

use App\Domain\Framework\Models\CompetencyFramework;
use App\Domain\Framework\Models\FrameworkVersion;
use Illuminate\Foundation\Http\FormRequest;

class FrameworkVersionRequest extends FormRequest
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
            'version' => [
                'required',
                'string',
                'max:40',
                function (string $attribute, mixed $value, \Closure $fail) use ($framework): void {
                    if ($framework === null) {
                        return;
                    }

                    $exists = FrameworkVersion::query()
                        ->where('framework_id', $framework->getKey())
                        ->where('version', $value)
                        ->exists();

                    if ($exists) {
                        $fail('Ce numéro de version existe déjà pour ce référentiel.');
                    }
                },
            ],
            'effective_date' => ['nullable', 'date'],
        ];
    }
}

<?php

namespace App\Http\Requests\Framework;

use App\Domain\Framework\Models\Competency;
use App\Domain\Framework\Models\CompetencyBlock;
use App\Domain\Framework\Models\FrameworkVersion;
use Illuminate\Foundation\Http\FormRequest;

class CompetencyRequest extends FormRequest
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
        /** @var FrameworkVersion|null $version */
        $version = $this->route('version');

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                function (string $attribute, mixed $value, \Closure $fail) use ($version): void {
                    if ($version === null) {
                        return;
                    }

                    $exists = Competency::query()
                        ->where('framework_version_id', $version->getKey())
                        ->where('code', $value)
                        ->exists();

                    if ($exists) {
                        $fail('Ce code de compétence existe déjà pour cette version.');
                    }
                },
            ],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'block_id' => [
                'nullable',
                'uuid',
                function (string $attribute, mixed $value, \Closure $fail) use ($version): void {
                    if ($value === null || $version === null) {
                        return;
                    }

                    $exists = CompetencyBlock::query()
                        ->where('framework_version_id', $version->getKey())
                        ->whereKey($value)
                        ->exists();

                    if (! $exists) {
                        $fail('Le bloc choisi ne fait pas partie de cette version.');
                    }
                },
            ],
            'parent_id' => [
                'nullable',
                'uuid',
                function (string $attribute, mixed $value, \Closure $fail) use ($version): void {
                    if ($value === null || $version === null) {
                        return;
                    }

                    $exists = Competency::query()
                        ->where('framework_version_id', $version->getKey())
                        ->whereKey($value)
                        ->exists();

                    if (! $exists) {
                        $fail('La compétence parente ne fait pas partie de cette version.');
                    }
                },
            ],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}

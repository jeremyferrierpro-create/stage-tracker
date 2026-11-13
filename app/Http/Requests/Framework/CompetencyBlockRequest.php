<?php

namespace App\Http\Requests\Framework;

use App\Domain\Framework\Models\CompetencyBlock;
use App\Domain\Framework\Models\FrameworkVersion;
use Illuminate\Foundation\Http\FormRequest;

class CompetencyBlockRequest extends FormRequest
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

                    $exists = CompetencyBlock::query()
                        ->where('framework_version_id', $version->getKey())
                        ->where('code', $value)
                        ->exists();

                    if ($exists) {
                        $fail('Ce code de bloc existe déjà pour cette version.');
                    }
                },
            ],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}

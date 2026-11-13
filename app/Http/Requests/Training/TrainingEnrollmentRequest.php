<?php

namespace App\Http\Requests\Training;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Training\Models\TrainingEnrollment;
use App\Domain\Training\Models\TrainingProgram;
use Illuminate\Foundation\Http\FormRequest;

class TrainingEnrollmentRequest extends FormRequest
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
            'user_id' => [
                'required',
                'exists:users,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($workspace): void {
                    /** @var User|null $user */
                    $user = User::query()->find($value);

                    if ($user === null || $workspace === null || $workspace->roleFor($user) === null) {
                        $fail('Selected user must be an active member of this workspace.');
                    }
                },
                function (string $attribute, mixed $value, \Closure $fail): void {
                    /** @var TrainingProgram|null $program */
                    $program = TrainingProgram::query()->find($this->route('program'));

                    if ($program !== null && TrainingEnrollment::query()
                        ->where('user_id', $value)
                        ->where('training_program_id', $program->getKey())
                        ->exists()) {
                        $fail('Cet utilisateur est déjà inscrit à ce programme.');
                    }
                },
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => "L'inscrit est obligatoire.",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'inscrit',
            'start_date' => 'date de début',
            'end_date' => 'date de fin',
        ];
    }
}

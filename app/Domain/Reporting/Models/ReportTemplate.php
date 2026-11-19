<?php

namespace App\Domain\Reporting\Models;

use App\Domain\Identity\Models\Workspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle de rapport (T-062, MLD §13) : géré par les administrateurs de
 * l'espace, versionné via report_template_versions (aucune trame codée en
 * dur). Une version publiée est immuable.
 */
class ReportTemplate extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'name',
        'type',
        'description',
        'status',
    ];

    public const TYPES = [
        'memoir',
        'professional_file',
        'oral_presentation',
        'personal_review',
        'technical_review',
        'weekly_followup',
        'supervisor_followup',
    ];

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'memoir' => 'Mémoire',
        'professional_file' => 'Dossier professionnel',
        'oral_presentation' => 'Dossier de soutenance',
        'personal_review' => 'Bilan personnel',
        'technical_review' => 'Bilan technique',
        'weekly_followup' => 'Suivi hebdomadaire',
        'supervisor_followup' => 'Suivi maître de stage',
    ];

    public const STATUSES = ['draft', 'published', 'archived'];

    /**
     * Sections autorisées dans une trame (schema_json.sections).
     *
     * @var list<string>
     */
    public const ALLOWED_SECTIONS = [
        'identity',
        'activities',
        'daily_logs',
        'evidences',
        'competencies',
        'tasks',
        'commits',
        'collaboration',
        'synthesis',
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<ReportTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ReportTemplateVersion::class, 'template_id');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /**
     * Valide une trame (schema_json) : sections connues, liste non vide.
     *
     * @return list<string> erreurs (vide = valide)
     */
    public static function schemaErrors(mixed $schema): array
    {
        if (! is_array($schema)) {
            return ['La trame doit être un objet JSON.'];
        }

        $errors = [];
        $sections = $schema['sections'] ?? null;

        if (! is_array($sections) || $sections === []) {
            $errors[] = 'La trame doit contenir au moins une section (sections).';
        } else {
            foreach ($sections as $section) {
                if (! is_string($section) || ! in_array($section, self::ALLOWED_SECTIONS, true)) {
                    $errors[] = 'Section inconnue : '.json_encode($section);

                    continue;
                }
            }
        }

        if (array_key_exists('title', $schema) && ! is_string($schema['title'])) {
            $errors[] = 'Le titre (title) doit être une chaîne.';
        }

        return $errors;
    }
}

<?php

namespace Tests\Feature\Backup;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use App\Domain\Internship\Models\Internship;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Project\Models\Activity;
use App\Domain\Project\Models\Project;
use App\Support\Backup\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * T-065 / MT-12 : sauvegarde (base + fichiers de preuves), restauration
 * sur un environnement altéré, contrôle par empreintes SHA-256.
 */
class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->owner->id,
            'role' => 'owner',
        ]);

        Storage::fake('local');
    }

    private function backupDir(): string
    {
        return sys_get_temp_dir().'/st_backup_'.Str::uuid();
    }

    /**
     * Jeu de données : stage, journal avec note privée, preuve fichier.
     *
     * @return array{internship: Internship, log: DailyLog}
     */
    private function seedData(): array
    {
        $internship = Internship::factory()->create(['workspace_id' => $this->workspace->id]);
        $project = Project::factory()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $this->workspace->id,
        ]);
        Activity::factory()->create([
            'project_id' => $project->id,
            'workspace_id' => $this->workspace->id,
            'title' => 'Activité sauvegardée',
        ]);

        $log = DailyLog::query()->create([
            'internship_id' => $internship->id,
            'workspace_id' => $this->workspace->id,
            'author_id' => $internship->trainee_id,
            'work_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'duration_minutes' => 480,
            'objectives' => 'Journée sauvegardée.',
            'work_done' => 'Travail consigné dans le journal.',
            'private_notes' => 'Note personnelle conservée en base uniquement.',
            'status' => 'closed',
        ]);

        Storage::disk('local')->put(
            'evidences/fichier-preuve.txt',
            'Contenu de la preuve à restaurer à l\'identique.',
        );

        return ['internship' => $internship, 'log' => $log];
    }

    public function test_mt12_restore_returns_data_and_files_identically(): void
    {
        $seed = $this->seedData();
        $log = $seed['log'];
        $service = new BackupService;
        $dir = $this->backupDir();

        // Sauvegarde + contrôle initial.
        $result = $service->backup($dir);
        $this->assertGreaterThan(0, $result['rows']);
        $this->assertTrue($service->verify($dir)['verified']);

        // Altération de l'environnement (perte de données + de fichier).
        DailyLog::query()->where('id', $log->id)->delete();
        $this->assertDatabaseMissing('daily_logs', ['id' => $log->id]);
        Storage::disk('local')->delete('evidences/fichier-preuve.txt');
        Storage::disk('local')->put('evidences/intrus.txt', 'fichier parasite');

        // Restauration.
        $restored = $service->restore($dir);
        $this->assertTrue($restored['verified']);

        // Les données sont récupérées à l'identique.
        $this->assertDatabaseHas('daily_logs', [
            'id' => $log->id,
            'work_done' => 'Travail consigné dans le journal.',
            'private_notes' => 'Note personnelle conservée en base uniquement.',
            'status' => 'closed',
        ]);
        $this->assertDatabaseHas('activities', ['title' => 'Activité sauvegardée']);

        // Les fichiers sont récupérés à l'identique, le parasite est parti.
        $this->assertSame(
            'Contenu de la preuve à restaurer à l\'identique.',
            Storage::disk('local')->get('evidences/fichier-preuve.txt'),
        );
        $this->assertFalse(Storage::disk('local')->exists('evidences/intrus.txt'));

        // Les séquences sont recadrées (insertion après restauration).
        $newLog = DailyLog::query()->create([
            'internship_id' => $seed['internship']->id,
            'workspace_id' => $this->workspace->id,
            'author_id' => $seed['internship']->trainee_id,
            'work_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'duration_minutes' => 120,
            'objectives' => 'Après restauration.',
            'work_done' => 'Nouvelle entrée valide.',
            'status' => 'draft',
        ]);
        $this->assertGreaterThan($log->id, $newLog->id);

        $this->cleanup($dir);
    }

    public function test_corrupted_archive_is_refused_before_any_write(): void
    {
        $this->seedData();
        $service = new BackupService;
        $dir = $this->backupDir();

        $service->backup($dir);

        // Altération d'un fichier de la base dans l'archive.
        $file = $dir.'/db/daily_logs.jsonl';
        file_put_contents($file, (string) file_get_contents($file).'{"corrompu":true}'."\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Empreinte invalide');

        try {
            $service->restore($dir);
        } finally {
            // Aucune écriture n'a eu lieu : les données sont intactes.
            $this->assertDatabaseHas('daily_logs', ['status' => 'closed']);
            $this->cleanup($dir);
        }
    }

    public function test_dry_run_verifies_fingerprints_without_writing(): void
    {
        $this->seedData();
        $service = new BackupService;
        $dir = $this->backupDir();

        $service->backup($dir);

        $result = $service->verify($dir);
        $this->assertTrue($result['verified']);
        $this->assertGreaterThan(10, $result['tables']);

        $this->cleanup($dir);
    }

    private function cleanup(string $dir): void
    {
        if (is_dir($dir)) {
            foreach (new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            ) as $item) {
                $item->isDir() ? rmdir((string) $item->getPathname()) : unlink((string) $item->getPathname());
            }
            rmdir($dir);
        }
    }
}

<?php

namespace Tests\Feature\Framework;

use App\Domain\Framework\Models\Competency;
use App\Domain\Framework\Models\CompetencyBlock;
use App\Domain\Framework\Models\CompetencyCriterion;
use App\Domain\Framework\Models\CompetencyFramework;
use App\Domain\Framework\Models\FrameworkVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\Workspace;
use App\Domain\Identity\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrameworkTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        return $user;
    }

    public function test_admin_creates_a_framework(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('frameworks.store'), [
                'name' => 'RÃ©fÃ©rentiel Test',
                'issuing_authority' => 'Organisme Test',
                'description' => 'Description test.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('competency_frameworks', [
            'name' => 'RÃ©fÃ©rentiel Test',
            'status' => 'draft',
        ]);
    }

    public function test_trainee_cannot_create_a_framework(): void
    {
        $trainee = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $trainee->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $trainee->id,
            'role' => 'trainee',
        ]);

        $this->actingAs($trainee)
            ->post(route('frameworks.store'), [
                'name' => 'Interdit',
            ])
            ->assertForbidden();

        $this->assertSame(0, CompetencyFramework::count());
    }

    public function test_version_and_hierarchy_can_be_created(): void
    {
        $admin = $this->adminUser();
        $framework = CompetencyFramework::create(['name' => 'RÃ©fÃ©rentiel '.uniqid(), 'status' => 'draft']);

        $this->actingAs($admin)
            ->post(route('frameworks.versions.store', $framework), [
                'version' => 'v1.0.0',
                'effective_date' => '2026-09-01',
            ])
            ->assertRedirect();

        $version = $framework->versions()->sole();

        $this->actingAs($admin)
            ->post(route('frameworks.blocks.store', $version), [
                'code' => 'B1',
                'title' => 'Bloc 1',
                'position' => 1,
            ])
            ->assertRedirect();

        $block = $version->blocks()->sole();

        $this->actingAs($admin)
            ->post(route('frameworks.competencies.store', $version), [
                'code' => 'C1.1',
                'title' => 'CompÃ©tence 1.1',
                'block_id' => $block->id,
                'position' => 1,
            ])
            ->assertRedirect();

        $competency = $version->competencies()->sole();

        $this->actingAs($admin)
            ->post(route('frameworks.criteria.store', $competency), [
                'label' => 'CritÃ¨re de validation',
                'position' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(1, $version->blocks()->count());
        $this->assertSame(1, $version->competencies()->count());
        $this->assertSame(1, $competency->criteria()->count());
    }

    public function test_duplicate_version_is_rejected(): void
    {
        $admin = $this->adminUser();
        $framework = CompetencyFramework::create(['name' => 'RÃ©fÃ©rentiel '.uniqid(), 'status' => 'draft']);
        FrameworkVersion::create([
            'framework_id' => $framework->id,
            'version' => 'v1.0.0',
        ]);

        $this->actingAs($admin)
            ->post(route('frameworks.versions.store', $framework), [
                'version' => 'v1.0.0',
            ])
            ->assertSessionHasErrors('version');
    }

    public function test_published_version_is_immutable_at_database_level(): void
    {
        $framework = CompetencyFramework::create(['name' => 'RÃ©fÃ©rentiel '.uniqid(), 'status' => 'draft']);
        $version = FrameworkVersion::create([
            'framework_id' => $framework->id,
            'version' => 'v1.0.0',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $block = CompetencyBlock::create([
            'framework_version_id' => $version->id,
            'code' => 'B1',
            'title' => 'Bloc',
        ]);

        try {
            DB::transaction(function () use ($block): void {
                DB::table('competency_blocks')
                    ->where('id', $block->id)
                    ->update(['title' => 'modifiÃ©']);
            });
            $this->fail('Le trigger PostgreSQL aurait dÃ» refuser la modification.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('immuable', $exception->getMessage());
        }

        try {
            DB::transaction(function () use ($version): void {
                DB::table('framework_versions')
                    ->where('id', $version->id)
                    ->update(['version' => 'v1.0.1']);
            });
            $this->fail('Le trigger PostgreSQL aurait dÃ» refuser la modification de la version publiÃ©e.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('immuable', $exception->getMessage());
        }
    }

    public function test_mt05_publishing_v2_keeps_v1_intact(): void
    {
        $admin = $this->adminUser();
        $framework = CompetencyFramework::create(['name' => 'RÃ©fÃ©rentiel '.uniqid(), 'status' => 'draft']);

        $v1 = FrameworkVersion::create([
            'framework_id' => $framework->id,
            'version' => 'v1.0.0',
            'status' => 'draft',
        ]);
        $block = CompetencyBlock::create([
            'framework_version_id' => $v1->id,
            'code' => 'B1',
            'title' => 'Bloc v1',
        ]);
        $competency = Competency::create([
            'framework_version_id' => $v1->id,
            'block_id' => $block->id,
            'code' => 'C1.1',
            'title' => 'CompÃ©tence v1',
        ]);
        CompetencyCriterion::create([
            'competency_id' => $competency->id,
            'label' => 'CritÃ¨re v1',
        ]);

        $publishResponse = $this->actingAs($admin)
            ->post(route('frameworks.versions.publish', $v1->id));
        $publishResponse->assertRedirect();

        $v1->refresh();
        $this->assertTrue($v1->isPublished());

        $storeResponse = $this->actingAs($admin)
            ->post(route('frameworks.versions.store', $framework), [
                'version' => 'v2.0.0',
            ]);
        $storeResponse->assertRedirect();

        $v2 = $framework->versions()->where('version', 'v2.0.0')->sole();
        $this->assertFalse($v2->isPublished());

        $this->assertSame(1, $v1->blocks()->count());
        $this->assertSame(1, $v1->competencies()->count());
        $this->assertSame(1, $v1->competencies()->first()->criteria()->count());
        $this->assertSame('Bloc v1', $v1->blocks()->first()->title);
        $this->assertSame('CompÃ©tence v1', $v1->competencies()->first()->title);
    }
}

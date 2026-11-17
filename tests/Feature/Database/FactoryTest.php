<?php

namespace Tests\Feature\Database;

use App\Domain\Evaluation\Models\Competency;
use App\Domain\Identity\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_competency_factory_matches_the_given_workspace(): void
    {
        $workspace = Workspace::factory()->create();

        $competency = Competency::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        $this->assertSame($workspace->id, $competency->workspace_id);
        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'workspace_id' => $workspace->id,
        ]);
    }
}

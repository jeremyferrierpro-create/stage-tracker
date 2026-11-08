<?php

namespace Tests\Feature\Database;

use App\Domain\Organization\Models\OrganizationMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_member_factory_matches_the_organization_workspace(): void
    {
        $member = OrganizationMember::factory()->create();

        $this->assertSame(
            $member->organization->workspace_id,
            $member->workspace_id
        );
    }
}

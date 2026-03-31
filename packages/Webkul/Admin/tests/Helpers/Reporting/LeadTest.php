<?php

namespace Webkul\Admin\Tests\Helpers\Reporting;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\User;
use Webkul\User\Models\Role;
use Webkul\Lead\Models\Lead;
use Webkul\Activity\Models\Activity as LeadActivity;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['name' => 'Recepcionista']);
        $this->user = User::factory()->create(['role_id' => $role->id]);
    }

    /** @test */
    public function it_calculates_average_stage_change_time_for_normal_flow()
    {
        $lead = Lead::factory()->create();

        LeadActivity::factory()->create([
            'lead_id' => $lead->id,
            'user_id' => $this->user->id,
            'created_at' => now()->subMinutes(30),
        ]);

        LeadActivity::factory()->create([
            'lead_id' => $lead->id,
            'user_id' => $this->user->id,
            'created_at' => now()->subMinutes(15),
        ]);

        $leadReporter = app(\Webkul\Admin\Helpers\Reporting\Lead::class);

        $result = $leadReporter->getAverageStageChangeTime();

        $this->assertEquals(900, $result['average_time']);
        $this->assertEquals('00:15', $result['formatted_time']);
    }

    /** @test */
    public function it_handles_leads_with_no_stage_changes()
    {
        $leadReporter = app(\Webkul\Admin\Helpers\Reporting\Lead::class);

        $result = $leadReporter->getAverageStageChangeTime();

        $this->assertEquals(0, $result['average_time']);
        $this->assertEquals('00:00', $result['formatted_time']);
    }

    /** @test */
    public function it_handles_leads_with_partial_changes()
    {
        $lead1 = Lead::factory()->create();
        $lead2 = Lead::factory()->create();

        LeadActivity::factory()->create([
            'lead_id' => $lead1->id,
            'user_id' => $this->user->id,
            'created_at' => now()->subMinutes(60),
        ]);

        LeadActivity::factory()->create([
            'lead_id' => $lead1->id,
            'user_id' => $this->user->id,
            'created_at' => now()->subMinutes(45),
        ]);

        LeadActivity::factory()->create([
            'lead_id' => $lead2->id,
            'user_id' => $this->user->id,
            'created_at' => now()->subMinutes(20),
        ]);

        $leadReporter = app(\Webkul\Admin\Helpers\Reporting\Lead::class);

        $result = $leadReporter->getAverageStageChangeTime();

        $this->assertEquals(900, $result['average_time']);
        $this->assertEquals('00:15', $result['formatted_time']);
    }
}

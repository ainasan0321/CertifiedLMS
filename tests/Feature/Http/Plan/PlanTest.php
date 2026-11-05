<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->coach = User::factory()->create(['role' => 'coach']);
    }

    public function test_admin_can_view_plan_index(): void
    {
        Plan::factory()->create(['status' => PlanStatus::Published]);

        $response = $this->actingAs($this->admin)->get(route('admin.plans.index'));

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_view_plan_index(): void
    {
        $this->actingAs($this->student)->get(route('admin.plans.index'))->assertStatus(403);

        $this->actingAs($this->coach)->get(route('admin.plans.index'))->assertStatus(403);
    }

    public function test_admin_can_store_plan_as_draft(): void
    {
        $payload = [
            'name' => 'Test Plan',
            'description' => 'Test Description',
            'duration_days' => 30,
            'default_meeting_quota' => 4,
            'sort_order' => 1,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.plans.store'), $payload);

        $response->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseHas('plans', [
            'name' => 'Test Plan',
            'status' => PlanStatus::Draft->value,
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_store_plan_validation_fails_with_invalid_data(): void
    {
        $payload = [
            'name' => '',
            'duration_days' => 99999,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.plans.store'), $payload);

        $response->assertSessionHasErrors(['name', 'duration_days']);
    }

    public function test_admin_can_publish_draft_plan(): void
    {
        $plan = Plan::factory()->create([
            'status' => PlanStatus::Draft,
            'created_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.plans.publish', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));

        $this->assertEquals(PlanStatus::Published, $plan->refresh()->status);
    }

    public function test_admin_can_delete_plan_without_students(): void
    {
        $plan = Plan::factory()->create([
            'created_by_user_id' => $this->admin->id,
            'updated_by_user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect(route('admin.plans.index'));
        $this->assertModelMissing($plan);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_show_store_update_destroy(): void
    {
        $user = User::factory()->create();
        $pos  = Position::factory()->create();
        $this->actingAs($user);

        // store
        $payload = [
            'code' => 'emp-0009',
            'first_name' => 'Nine',
            'last_name'  => 'Guy',
            'status' => 'active',
            'email' => 'nine@example.com',
            'position_id' => $pos->id,
        ];
        $res = $this->postJson('/api/employees', $payload)->assertCreated();
        $id = $res->json('data.id');

        // index
        $this->getJson('/api/employees?per_page=5')->assertOk()
            ->assertJsonStructure(['data']);

        // show
        $this->getJson("/api/employees/{$id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'emp-0009');

        // update
        $this->patchJson("/api/employees/{$id}", ['email' => 'nine2@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'nine2@example.com');

        // destroy
        $this->deleteJson("/api/employees/{$id}")
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}

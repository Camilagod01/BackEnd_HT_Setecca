<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Depends;


class EmployeesApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        return $user;
    }

    private function makePosition(array $overrides = []): Position
    {
        // Creamos un Position mínimo sin depender de factories
        return Position::create(array_merge([
            'code' => 'POS-' . fake()->unique()->numerify('###'),
            'name' => 'Tester',
            'salary_type' => 'monthly',           // o 'hourly' si aplica
            'default_salary_amount' => 1200,
            'default_salary_currency' => 'USD',
            'base_hourly_rate' => 0,
            'currency' => 'CRC',
        ], $overrides));
    }

    private function makeEmployee(Position $pos, array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'code' => 'emp-' . fake()->unique()->numerify('####'),
            'first_name' => 'Nombre',
            'last_name'  => 'Apellido',
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
            'position_id' => $pos->id,
            'garnish_cap_rate' => null,
        ], $overrides));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_lists_employees_with_pagination_sort_and_search()
    {
        $this->auth();
        $pos = $this->makePosition();
        $e1  = $this->makeEmployee($pos, ['code'=>'emp-0001','first_name'=>'Primer','last_name'=>'Usuario','email'=>'primer@example.com']);
        $e2  = $this->makeEmployee($pos, ['code'=>'emp-0002','first_name'=>'Segundo','last_name'=>'Usuario','email'=>'segundo@example.com']);

        // list paginado
        $resp = $this->getJson('/api/employees?per_page=5&sort=code&dir=asc');
        $resp->assertOk()
             ->assertJsonPath('data.0.code', 'emp-0001');

        // search
        $resp = $this->getJson('/api/employees?search=Primer');
        $resp->assertOk()
             ->assertJsonFragment(['code' => 'emp-0001']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_one_employee()
    {
        $this->auth();
        $pos = $this->makePosition();
        $emp = $this->makeEmployee($pos, ['code'=>'emp-0010','email'=>'uno@example.com']);

        $this->getJson("/api/employees/{$emp->id}")
             ->assertOk()
             ->assertJsonPath('data.id', $emp->id)
             ->assertJsonPath('data.position_id', $pos->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_employee_and_validates_uniques()
    {
        $this->auth();
        $pos = $this->makePosition();

        // create
        $payload = [
            'code' => 'emp-0100',
            'first_name' => 'Nuevo',
            'last_name'  => 'Empleado',
            'email' => 'nuevo@example.com',
            'status' => 'active',
            'position_id' => $pos->id,
            'garnish_cap_rate' => 0.33,
        ];
        $this->postJson('/api/employees', $payload)
             ->assertCreated()
             ->assertJsonPath('data.code', 'emp-0100')
             ->assertJsonPath('data.position_id', $pos->id);

        // duplicated code/email
        $this->postJson('/api/employees', $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['code','email']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_employee_fields()
    {
        $this->auth();
        $pos = $this->makePosition();
        $emp = $this->makeEmployee($pos, ['email'=>'old@example.com', 'garnish_cap_rate'=>null]);

        $this->patchJson("/api/employees/{$emp->id}", [
            'email' => 'new@example.com',
            'garnish_cap_rate' => 0.30,
        ])->assertOk()
          ->assertJsonPath('data.email', 'new@example.com')
          ->assertJsonPath('data.garnish_cap_rate', 0.30);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_employee_position_with_validation()
    {
        $this->auth();
        $pos1 = $this->makePosition(['code' => 'POS-100']);
        $pos2 = $this->makePosition(['code' => 'POS-200']);
        $emp  = $this->makeEmployee($pos1);

        // ok
        $this->patchJson("/api/employees/{$emp->id}/position", [
            'position_id' => $pos2->id,
        ])->assertOk()
          ->assertJsonPath('position_id', $pos2->id);

        // invalid
        $this->patchJson("/api/employees/{$emp->id}/position", [
            'position_id' => 999999,
        ])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_employees_options_minimal_payload()
    {
        $this->auth();
        $pos = $this->makePosition();
        $emp = $this->makeEmployee($pos, ['code'=>'emp-0200','first_name'=>'Mini','last_name'=>'Card']);

        $this->getJson('/api/employees/options')
             ->assertOk()
             ->assertJsonStructure(['data' => [['id','code','full_name']]])
             ->assertJsonFragment(['code'=>'emp-0200']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_employee()
    {
        $this->auth();
        $pos = $this->makePosition();
        $emp = $this->makeEmployee($pos);

        $this->deleteJson("/api/employees/{$emp->id}")
             ->assertOk()
             ->assertJson(['ok' => true, 'id' => $emp->id]);

        $this->getJson("/api/employees/{$emp->id}")
             ->assertStatus(404);
    }
}

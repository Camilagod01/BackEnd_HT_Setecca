<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Employee;
use App\Models\Position;
use PHPUnit\Framework\Attributes\Test;


class PayrollPreviewTest extends TestCase
{
    use RefreshDatabase;

   #[Test]
        public function fusiona_intervalos_solapados_y_calcula_horas_sin_sobreconteo(): void
        {
        // Seed mínimo
        $user = User::factory()->create();
        $this->actingAs($user);

        $pos = Position::factory()->create([
            'salary_type' => 'monthly',
            'default_salary_amount' => 1200,
            'default_salary_currency' => 'USD',
        ]);

        $emp = Employee::factory()->create([
            'position_id' => $pos->id,
            'status'      => 'active',
            'code'        => 'emp-test-001',
        ]);

        // Inserta 2 bloques que se traslapan el mismo día (08-12 y 11:50-16)
        DB::table('time_entries')->insert([
            [
                'employee_id' => $emp->id,
                'work_date'   => '2025-11-05',
                'check_in'    => '2025-11-05 08:00:00',
                'check_out'   => '2025-11-05 12:00:00',
                'notes'       => 'bloque A',
            ],
            [
                'employee_id' => $emp->id,
                'work_date'   => '2025-11-05',
                'check_in'    => '2025-11-05 11:50:00',
                'check_out'   => '2025-11-05 16:00:00',
                'notes'       => 'bloque B',
            ],
        ]);

        // Ejecuta el endpoint real
        $response = $this->getJson("/api/payroll/preview?employee_id={$emp->id}&from=2025-11-01&to=2025-11-15");

        $response->assertOk();
        $data = $response->json();

        $this->assertTrue($data['ok']);

        // Busca el daily del 05
        $day = collect($data['daily'])->firstWhere('date', '2025-11-05 00:00:00');

        $this->assertEquals(8.00, $day['hours_total']);
        $this->assertEquals(0.00, $day['hours_overtime']);

        // En entries debe haber un solo bloque consolidado 08:00–16:00
        $entry = collect($data['entries'])->firstWhere('work_date', '2025-11-05 00:00:00');
        $this->assertEquals('2025-11-05 08:00:00', $entry['check_in']);
        $this->assertEquals('2025-11-05 16:00:00', $entry['check_out']);
        $this->assertEquals(8.00, $entry['hours']);
    }
}

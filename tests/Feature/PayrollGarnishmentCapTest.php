<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Employee;
use App\Models\Position;

class PayrollGarnishmentCapTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplica_tope_de_embargos_y_no_sobrepasa_el_cap()
    {
        // Usuario autenticado
        $user = User::factory()->create();
        $this->actingAs($user);

        // Puesto con salario mensual (igual que el escenario que ya probaste)
        $pos = Position::factory()->create([
            'salary_type'              => 'monthly',
            'default_salary_amount'    => 1200,   // USD
            'default_salary_currency'  => 'USD',
        ]);

        // Empleado con cap del 30%
        $emp = Employee::factory()->create([
            'position_id'      => $pos->id,
            'status'           => 'active',
            'code'             => 'emp-cap-001',
            'garnish_cap_rate' => 0.30,
        ]);

        // Carga de time_entries dentro del rango 01–15 nov (mismo patrón que tu ejemplo)
        // 2025-11-03: 09:00h a 17:30h = 9.5h
        // 2025-11-04: 08:00h a 16:00h = 8h
        // 2025-11-07: feriado 8h (tu servicio ya marca holiday por fecha)
        DB::table('time_entries')->insert([
            [
                'employee_id' => $emp->id,
                'work_date'   => '2025-11-03',
                'check_in'    => '2025-11-03 08:00:00',
                'check_out'   => '2025-11-03 17:30:00',
                'notes'       => '9.5h',
            ],
            [
                'employee_id' => $emp->id,
                'work_date'   => '2025-11-04',
                'check_in'    => '2025-11-04 08:00:00',
                'check_out'   => '2025-11-04 16:00:00',
                'notes'       => '8h',
            ],
            [
                'employee_id' => $emp->id,
                'work_date'   => '2025-11-07',
                'check_in'    => '2025-11-07 08:00:00',
                'check_out'   => '2025-11-07 16:00:00',
                'notes'       => 'feriado 8h',
            ],
        ]);

        // Embargos activos en la quincena (mismos que usaste: 15%, 12.5%, y ₡20,000)
        DB::table('garnishments')->insert([
            [
                'employee_id' => $emp->id,
                'order_no'    => null,
                'mode'        => 'percent',
                'value'       => 15.0,
                'start_date'  => '2017-06-17',
                'end_date'    => null,
                'priority'    => 1,
                'active'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'employee_id' => $emp->id,
                'order_no'    => null,
                'mode'        => 'percent',
                'value'       => 12.5,
                'start_date'  => '2025-11-12',
                'end_date'    => null,
                'priority'    => 1,
                'active'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'employee_id' => $emp->id,
                'order_no'    => null,
                'mode'        => 'amount',
                'value'       => 20000, // CRC
                'start_date'  => '2025-11-10',
                'end_date'    => null,
                'priority'    => 1,
                'active'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);

        // Ejecuta el preview real
        $resp = $this->getJson("/api/payroll/preview?employee_id={$emp->id}&from=2025-11-01&to=2025-11-15");
        $resp->assertOk();

        $data  = $resp->json();
        $money = $data['money'];
        $items = $data['garnishments'];

        // Aserciones clave del CAP
        $this->assertArrayHasKey('garnish_cap', $money);
        $cap = $money['garnish_cap'];

        // 30% aplicado
        $this->assertEquals(0.30, (float)$cap['percent']);

        $gross = (float)$money['total_amount'];
        $capAmount = round($gross * 0.30, 2);

        // El cap calculado debe coincidir
        $this->assertEquals($capAmount, (float)$cap['cap_amount']);

        // used debe ser igual a cap_amount (porque con esos 3 embargos se alcanza el tope)
        $this->assertEquals($capAmount, (float)$cap['used']);
        $this->assertEquals(0.0, (float)$cap['remaining']);

        // El neto debe ser bruto - cap
        $this->assertEquals(
            round($gross - $capAmount, 2),
            (float)$money['net_amount']
        );

        // Alguno de los embargos de monto puede quedar parcialmente aplicado o marcado como capped=true en tu implementación
        // Verificamos que la suma de deductions no exceda el cap
        $sum = array_sum(array_map(fn($i) => (float)$i['deduction'], $items));
        $this->assertEquals($capAmount, round($sum, 2));
    }
}

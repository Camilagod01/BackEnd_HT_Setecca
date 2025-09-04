<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Employee;

class EmployeeResetSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('time_entries')->truncate();
        DB::table('employees')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Sembrar emp-0001 ... emp-2000
        for ($i = 1; $i <= 200; $i++) {
            $code = sprintf('emp-%04d', $i);
            Employee::create([
                'code'       => $code,
                'first_name' => 'Empleado',
                'last_name'  => (string) $i,
            ]);
        }
    }
}

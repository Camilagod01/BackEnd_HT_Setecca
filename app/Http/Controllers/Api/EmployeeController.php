<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\AuditsChanges;

class EmployeeController extends Controller
{
    use AuditsChanges;

    // GET /api/employees
    public function index(Request $request)
    {
        $q = Employee::query()
            ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
            ->select('employees.*', 'positions.name as position_name')
            ->orderBy('employees.id', 'desc');

        if ($s = $request->get('search')) {
            $q->where(function ($qq) use ($s) {
                $qq->where('employees.code', 'like', "%{$s}%")
                    ->orWhere('employees.first_name', 'like', "%{$s}%")
                    ->orWhere('employees.last_name', 'like', "%{$s}%")
                    ->orWhere('employees.email', 'like', "%{$s}%");
            });
        }

        if ($status = $request->get('status')) {
            $q->where('employees.status', $status);
        }

        if ($pid = $request->get('position_id')) {
            $q->where('employees.position_id', $pid);
        }

        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0 || $perPage > 100) $perPage = 15;

        return response()->json($q->paginate($perPage));
    }

    // GET /api/employees/{id}
    public function show($id)
    {
        $emp = Employee::with('position')->findOrFail($id);
        return response()->json($emp);
    }

    // POST /api/employees
    public function store(Request $request)
    {
        $data = $request->validate([
            'code'       => ['nullable','string','max:255','unique:employees,code'],
            'first_name' => ['required','string','max:255'],
            'last_name'  => ['required','string','max:255'],
            'email'      => ['nullable','email','max:255','unique:employees,email'],
            'hire_date'  => ['nullable','date'],
            'status'     => ['nullable', Rule::in(['active','inactive'])],
            'position_id'=> ['nullable','exists:positions,id'],

            'use_position_salary'      => ['boolean'],
            'salary_type'              => ['required_if:use_position_salary,false','in:monthly,hourly'],
            'salary_override_amount'   => ['required_if:use_position_salary,false','numeric','min:0'],
            'salary_override_currency' => ['required_if:use_position_salary,false','in:CRC,USD'],
        ]);

        // defaults seguros
        $data['status'] = $data['status'] ?? 'active';
        $data['use_position_salary'] = $data['use_position_salary'] ?? true;

        if ($data['use_position_salary'] && empty($data['position_id'])) {
            return response()->json([
                'message' => 'El puesto (position_id) es requerido cuando use_position_salary=true.'
            ], 422);
        }

        // Si usa salario del puesto, setear valores por defecto coherentes
        if ($data['use_position_salary']) {
            $data['salary_type'] = 'monthly';
            $data['salary_override_amount'] = 0;
            $data['salary_override_currency'] = 'CRC';
        }

        $emp = Employee::create($data);
        $emp->load('position');

        if (method_exists($this, 'audit')) {
            $this->audit($emp, [], $emp->toArray(), 'created');
        }

        return response()->json($emp, 201);
    }

    // PATCH /api/employees/{id}
    public function update(Request $request, $id)
    {
        $emp = Employee::find($id);
        if (!$emp) return response()->json(['message' => 'Empleado no encontrado'], 404);

        $data = $request->validate([
            'code'       => ['sometimes','string','max:255', Rule::unique('employees','code')->ignore($emp->id)],
            'first_name' => ['sometimes','string','max:255'],
            'last_name'  => ['sometimes','string','max:255'],
            'email'      => ['sometimes','nullable','email','max:255', Rule::unique('employees','email')->ignore($emp->id)],
            'hire_date'  => ['sometimes','nullable','date'],
            'status'     => ['sometimes', Rule::in(['active','inactive'])],
            'position_id'=> ['sometimes','nullable','exists:positions,id'],

            'use_position_salary'      => ['sometimes','boolean'],
            'salary_type'              => ['sometimes','in:monthly,hourly'],
            'salary_override_amount'   => ['sometimes','nullable','numeric','min:0'],
            'salary_override_currency' => ['sometimes','in:CRC,USD'],
        ]);

        if (
            array_key_exists('use_position_salary', $data)
            && $data['use_position_salary'] === true
            && empty($data['position_id'] ?? $emp->position_id)
        ) {
            return response()->json([
                'message' => 'El puesto (position_id) es requerido cuando use_position_salary=true.'
            ], 422);
        }

        $before = $emp->toArray();
        $emp->fill($data);
        $emp->save();
        $emp->load('position');

        if (method_exists($this, 'audit')) {
            $this->audit($emp, $before, $emp->fresh()->toArray(), 'patched');
        }

        return response()->json($emp, 200);
    }

    // PATCH /api/employees/{employee}/position
    public function updatePosition(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'position_id' => ['nullable','exists:positions,id'],
            'use_position_salary' => ['sometimes','boolean'],
        ]);

        if (($data['use_position_salary'] ?? $employee->use_position_salary) === true) {
            $pid = $data['position_id'] ?? $employee->position_id;
            if (empty($pid)) {
                return response()->json([
                    'message' => 'El puesto (position_id) es requerido cuando use_position_salary=true.'
                ], 422);
            }
        }

        $before = $employee->toArray();

        $employee->position_id = $data['position_id'] ?? null;
        if (array_key_exists('use_position_salary', $data)) {
            $employee->use_position_salary = $data['use_position_salary'];
        }

        // Normalizar para evitar nulls indebidos
        if ($employee->use_position_salary) {
            $employee->salary_type = $employee->salary_type ?? 'monthly';
            $employee->salary_override_amount = $employee->salary_override_amount ?? 0;
            $employee->salary_override_currency = $employee->salary_override_currency ?? 'CRC';
        }

        $employee->save();
        $employee->load('position');

        if (method_exists($this, 'audit')) {
            $this->audit($employee, $before, $employee->fresh()->toArray(), 'position_changed');
        }

        return response()->json($employee);
    }

    // DELETE /api/employees/{id}
    public function destroy($id)
    {
        $emp = Employee::find($id);
        if (!$emp) return response()->json(['message' => 'Empleado no encontrado'], 404);

        $before = $emp->toArray();
        $emp->delete();

        if (method_exists($this, 'audit')) {
            $this->audit($emp, $before, [], 'deleted');
        }

        return response()->json(['message' => 'Eliminado']);
    }
}

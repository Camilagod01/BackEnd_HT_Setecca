<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    // GET /api/employees?search=&status=&page=
    public function index()
{
    $employees = \App\Models\Employee::leftJoin('positions', 'employees.position_id', '=', 'positions.id')
        ->select('employees.*', 'positions.name as position_name')
        ->orderBy('employees.id', 'desc')
        ->paginate(10);

    return response()->json($employees);
}


    // GET /api/employees/{id}
    public function show($id)
    {
        $emp = \App\Models\Employee::with('position')->findOrFail($id);
    return response()->json($emp);
    }

    // POST /api/employees
    /*public function store(Request $request)
    {
        $data = $request->validate([
            'code'       => ['required','string','max:255','unique:employees,code'],
            'first_name' => ['required','string','max:255'],
            'last_name'  => ['required','string','max:255'],
            'email'      => ['nullable','email','max:255','unique:employees,email'],
            'position'   => ['nullable','string','max:255'],
            'hire_date'  => ['nullable','date'],
            'status'     => ['required', Rule::in(['active','inactive'])],
        ]);

        $employee = Employee::create($data);
        return response()->json($employee, 201);
    }*/

public function store(Request $request)
{
    $data = $request->validate([
        'first_name' => 'required|string|max:255',
        'last_name'  => 'required|string|max:255',
        'email'      => 'nullable|email|unique:employees,email',
        'position_id' => ['required','exists:positions,id'],
        'hire_date'  => 'nullable|date',
        'status'     => 'nullable|in:active,inactive',
        'code'       => 'nullable|string|max:255|unique:employees,code',
    ]);

    // Defaults
    if (!isset($data['status'])) $data['status'] = 'active';

    $employee = new \App\Models\Employee($data);
    $employee->save();

    return response()->json($employee, 201);
}





    // PUT /api/employees/{id}
    public function update(Request $request, $id)
    {
        $employee = Employee::find($id);
        if (!$employee) {
            return response()->json(['message' => 'Empleado no encontrado'], 404);
        }

        $data = $request->validate([
            'code'       => ['sometimes','string','max:255', Rule::unique('employees','code')->ignore($employee->id)],
            'first_name' => ['sometimes','string','max:255'],
            'last_name'  => ['sometimes','string','max:255'],
            'email'      => ['nullable','email','max:255', Rule::unique('employees','email')->ignore($employee->id)],
            'position'   => ['nullable','string','max:255'],
            'hire_date'  => ['nullable','date'],
            'status'     => ['sometimes', Rule::in(['active','inactive'])],
        ]);

         $emp = \App\Models\Employee::create($data);

    // opcional: eager load
    $emp->load('position');

    return response()->json($emp, 201);
    }

    // DELETE /api/employees/{id}
    public function destroy($id)
    {
        $employee = Employee::find($id);
        if (!$employee) {
            return response()->json(['message' => 'Empleado no encontrado'], 404);
        }
        $employee->delete();
        return response()->json(['message' => 'Eliminado']);
    }

 public function updatePosition(Request $request, Employee $employee)
{
    $data = $request->validate([
        'position_id' => ['nullable','exists:positions,id'],
    ]);

    $employee->position_id = $data['position_id'] ?? null;
    $employee->save();

    $employee->load('position'); // para que el front reciba el nombre del puesto
    return response()->json($employee);
}
}

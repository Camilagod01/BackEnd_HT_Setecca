<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    // GET /api/employees?search=&status=&page=
    public function index(Request $request)
    {
        $q = Employee::query();

        if ($s = $request->get('search')) {
            $q->where(function ($sub) use ($s) {
                $sub->where('code', 'like', "%{$s}%")
                    ->orWhere('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            });
        }

        if ($status = $request->get('status')) {
            $q->where('status', $status); // 'active' | 'inactive'
        }

        return response()->json($q->orderBy('id', 'desc')->paginate(20));
    }

    // GET /api/employees/{id}
    public function show($id)
    {
        $employee = Employee::find($id);
        if (!$employee) {
            return response()->json(['message' => 'Empleado no encontrado'], 404);
        }
        return response()->json($employee);
    }

    // POST /api/employees
    public function store(Request $request)
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

        $employee->update($data);
        return response()->json($employee);
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
}

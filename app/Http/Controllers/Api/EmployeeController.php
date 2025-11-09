<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $q = Employee::query()
            ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
            ->select('employees.*', 'positions.name as position_name');

        // filtros
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

        // orden
        $sort = $request->query('sort', 'employees.id');
        $dir  = $request->query('dir', 'desc');
        if (!in_array(strtolower($dir), ['asc','desc'])) $dir = 'desc';
        $q->orderBy($sort, $dir);

        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0 || $perPage > 100) $perPage = 15;

        $page = $q->paginate($perPage);

        // transforma cada item con resource “liviano” para lista
        $page->getCollection()->transform(function ($emp) {
            return [
                'id'            => $emp->id,
                'code'          => $emp->code,
                'first_name'    => $emp->first_name,
                'last_name'     => $emp->last_name,
                'full_name'     => trim(($emp->first_name ?? '').' '.($emp->last_name ?? '')),
                'email'         => $emp->email,
                'position_id'   => $emp->position_id,
                'position_name' => $emp->position_name,
                'status'        => $emp->status,
                'hire_date'     => optional($emp->hire_date)->toDateString(),
            ];
        });

        return response()->json($page);
    }

    public function show($id)
    {
        $emp = Employee::with('position')->findOrFail($id);
        return response()->json(['data' => EmployeeResource::make($emp)]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();
        $emp  = Employee::create($data);

        // si querés devolver el resource completo:
        $emp->load('position');
        return response()->json(['data' => EmployeeResource::make($emp)], 201);
    }

    /*public function update(UpdateEmployeeRequest $request, $id)
    {
        $emp  = Employee::findOrFail($id);
        $data = $request->validated();

        $emp->fill($data)->save();

        $emp->load('position');
        return response()->json(['data' => EmployeeResource::make($emp)]);
    }*/


    public function update(Request $request, $id)
{
    $emp = Employee::findOrFail($id);

    $data = $request->validate([
        'first_name'        => 'sometimes|string|max:100',
        'last_name'         => 'sometimes|string|max:100',
        'email'             => 'sometimes|email|unique:employees,email,'.$emp->id,
        'status'            => 'sometimes|in:active,inactive',
        'position_id'       => 'sometimes|exists:positions,id',
        'garnish_cap_rate'  => 'nullable|numeric|min:0|max:1', // <- AÑADIR
    ]);

    $emp->fill($data); // <- con fillable ya incluirá garnish_cap_rate
    $emp->save();

    // si usas Resource:
    return response()->json(['data' => new \App\Http\Resources\EmployeeResource($emp->fresh())]);

    // o, si devuelves el modelo:
    // return response()->json(['data' => $emp->fresh()]);
}





    public function destroy($id)
    {
        $emp = Employee::findOrFail($id);
        $emp->delete();

        return response()->json(['ok' => true, 'id' => (int)$id, 'msg' => 'Employee deleted']);
    }

    /** Dropdown liviano: id, code, full_name */
    public function options()
    {
        $rows = Employee::query()
            ->select(['id','code','first_name','last_name'])
            ->orderBy('code','asc')
            ->get()
            ->map(fn($e) => [
                'id'        => $e->id,
                'code'      => $e->code,
                'full_name' => trim(($e->first_name ?? '').' '.($e->last_name ?? '')),
            ]);

        return response()->json(['data' => $rows]);
    }

    /** PATCH /employees/{employee}/position  body: { position_id } */
    public function updatePosition(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'position_id' => ['required','integer','exists:positions,id'],
        ]);

        $employee->position_id = $data['position_id'];
        $employee->save();

        $employee->load('position');
        return response()->json(EmployeeResource::make($employee));
    }
}

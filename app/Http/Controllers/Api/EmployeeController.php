<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeStoreRequest;
use App\Http\Requests\EmployeeUpdateRequest;
use App\Models\Employee;
use App\Traits\AuditsChanges;
use Illuminate\Http\Request;


class EmployeeController extends Controller
{
     use AuditsChanges;

    public function index(Request $req)
    {
        $q = Employee::query();

        if ($s = $req->query('search')) {
            $q->where(function ($qq) use ($s) {
                $qq->where('code', 'like', "%{$s}%")
                   ->orWhere('first_name', 'like', "%{$s}%")
                   ->orWhere('last_name', 'like', "%{$s}%")
                   ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $perPage = (int) ($req->query('per_page', 15));
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        return $q->orderBy('id','desc')->paginate($perPage);
    }

    public function show($id)
    {
        return Employee::findOrFail($id);
    }

    public function store(EmployeeStoreRequest $req)
    {
        $emp = Employee::create($req->validated());
        // Opcional: registrar creación
        $this->audit($emp, [], $emp->toArray(), 'created');
        return response()->json($emp, 201);
    }

    public function update($id, EmployeeUpdateRequest $req)
    {
        $emp = Employee::findOrFail($id);
        $before = $emp->toArray();

        $emp->fill($req->validated());
        $emp->save();

        $this->audit($emp, $before, $emp->fresh()->toArray(), 'patched');

        return $emp;
    }
}

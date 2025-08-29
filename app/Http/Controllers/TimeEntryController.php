<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    // GET /api/time-entries?date=YYYY-MM-DD&employee_id=&page=
    public function index(Request $request)
    {
        $q = TimeEntry::with('employee');

        if ($date = $request->get('date')) {
            $q->whereDate('work_date', $date);
        }

        if ($emp = $request->get('employee_id')) {
            $q->where('employee_id', $emp);
        }

        return response()->json($q->orderBy('work_date','desc')->orderBy('id','desc')->paginate(20));
    }

    // POST /api/time-entries
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required','exists:employees,id'],
            'work_date'   => ['required','date'],
            'check_in'    => ['required','date_format:Y-m-d H:i:s'],
            'check_out'   => ['nullable','date_format:Y-m-d H:i:s','after:check_in'],
            'source'      => ['required','in:portal,forms_csv'],
            'notes'       => ['nullable','string'],
        ]);

        $entry = TimeEntry::create($data);

        return response()->json($entry->load('employee'), 201);
    }
}

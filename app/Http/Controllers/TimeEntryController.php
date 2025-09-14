<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use App\Traits\AuditsChanges;  
use Carbon\Carbon;

class TimeEntryController extends Controller
{
     use AuditsChanges; 
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

        return response()->json(
            $q->orderBy('work_date','desc')
            ->orderBy('id','desc')
            ->paginate(20));
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

          if (!empty($entry->check_out)) {
            $entry->hours_worked = $this->calcHours($entry->check_in, $entry->check_out);
            $entry->save();
        }

        return response()->json($entry->load('employee'), 201);
    }
      // PATCH /api/time-entries/{id}
    public function update($id, Request $request)
    {
        $entry  = TimeEntry::findOrFail($id);
        $before = $entry->toArray();

        $data = $request->validate([
            'work_date' => ['sometimes','date'],
            'check_in'  => ['sometimes','date_format:Y-m-d H:i:s'],
            'check_out' => ['sometimes','nullable','date_format:Y-m-d H:i:s','after:check_in'],
            'source'    => ['sometimes','in:portal,forms_csv'],
            'notes'     => ['sometimes','nullable','string'],
            'status'    => ['sometimes','in:valid,corrected,rejected'], // si usas status
        ]);

        $entry->fill($data);

        // Recalcular horas si tocaste check_in/check_out o si ahora ambos existen
        if (array_key_exists('check_in', $data) || array_key_exists('check_out', $data)) {
            if (!empty($entry->check_in) && !empty($entry->check_out)) {
                $entry->hours_worked = $this->calcHours($entry->check_in, $entry->check_out);
            } else {
                // si falta uno, deja horas en null o 0 según tu diseño
                $entry->hours_worked = null;
            }
        }

        $entry->save();

        // auditoría (antes/después)
        $this->audit($entry, $before, $entry->fresh()->toArray(), 'patched');

        return response()->json($entry->load('employee'));
    }

    // helper privado para horas trabajadas
    private function calcHours(string $in, string $out): float
    {
        $start = Carbon::parse($in);
        $end   = Carbon::parse($out);
        return max(0, $end->floatDiffInHours($start)); // evita negativos
    }
}


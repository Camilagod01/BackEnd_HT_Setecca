<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Requests\TimeEntryUpdateRequest;
use App\Models\TimeEntry;
use App\Traits\AuditsChanges;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    use AuditsChanges;

    public function index(Request $req)
    {
        $q = TimeEntry::query();

        if ($eid = $req->query('employee_id')) {
            $q->where('employee_id', $eid);
        }
        if ($from = $req->query('from')) {
            $q->whereDate('in_at', '>=', $from);
        }
        if ($to = $req->query('to')) {
            $q->whereDate('out_at', '<=', $to);
        }
        if ($st = $req->query('status')) {
            $q->where('status', $st);
        }

        return $q->orderBy('in_at','desc')->paginate(20);
    }

    public function update($id, TimeEntryUpdateRequest $req)
    {
        $t = TimeEntry::findOrFail($id);
        $before = $t->toArray();

        $t->fill($req->validated());

        // Recalcular horas si hay in/out
        if (!empty($t->in_at) && !empty($t->out_at)) {
            $in  = Carbon::parse($t->in_at);
            $out = Carbon::parse($t->out_at);
            $t->hours_worked = max(0, $out->floatDiffInHours($in)); // evita negativos
        }

        $t->save();

        $this->audit($t, $before, $t->fresh()->toArray(), 'patched');

        return $t;
    }
}

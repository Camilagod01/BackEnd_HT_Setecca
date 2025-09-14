<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
  'table_name','record_id','user_id','action','before_values','after_values','ip','user_agent'
];
protected $casts = ['before_values'=>'array','after_values'=>'array'];

}

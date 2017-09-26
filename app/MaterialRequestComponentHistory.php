<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MaterialRequestComponentHistory extends Model
{
    protected $table = 'material_request_component_history_table';

    protected $fillable = ['material_request_component_id','component_status_id','user_id','remark'];
}
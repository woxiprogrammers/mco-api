<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ChecklistStatus extends Model
{
    protected $table = 'checklist_statuses';

    protected $fillable = ['name','slug'];
}

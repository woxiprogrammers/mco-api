<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ChecklistCategory extends Model
{
    protected $table = 'checklist_categories';

    protected $fillable = ['name','slug','is_active','category_id'];
}

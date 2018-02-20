<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DprMainCategory extends Model
{
    protected $table = 'dpr_main_categories';
    protected $fillable = ['name','status'];
}

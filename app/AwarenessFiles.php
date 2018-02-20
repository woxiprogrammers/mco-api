<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AwarenessFiles extends Model
{
    protected $table = 'awareness_files';

    protected $fillable = ['awareness_main_category_id','awareness_sub_category_id','file_name'];
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DprDetailImageRelation extends Model
{
    protected $table = 'dpr_detail_image_relations';

    protected $fillable = ['dpr_detail_id','dpr_image_id'];
}

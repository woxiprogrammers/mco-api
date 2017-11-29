<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DrawingCategorySiteRelation extends Model
{
    protected $table = 'drawing_category_site_relations';
    protected $fillable = ['project_site_id','drawing_category_id'];
}

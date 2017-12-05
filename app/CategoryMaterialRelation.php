<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CategoryMaterialRelation extends Model
{
    protected $table = 'category_material_relations';

    protected $fillable = ['category_id','material_id'];

    public function category()
    {
        return $this->hasMany('App\Category');
    }

    public function material()
    {
        return $this->hasMany('App\Material');
    }
}

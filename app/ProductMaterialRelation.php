<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductMaterialRelation extends Model
{
    protected $table = 'product_material_relation';

    protected $fillable = ['product_version_id','material_version_id','material_quantity'];

    public function product_version(){
        return $this->belongsTo('App\ProductVersion','product_version_id');
    }

    public function material_version(){
        return $this->belongsTo('App\MaterialVersion','material_version_id');
    }
}

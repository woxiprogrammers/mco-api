<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductVersion extends Model
{
    protected $table = 'product_versions';

    protected $fillable = ['product_id','rate_per_unit'];

    public function product(){
        return $this->belongsTo('App\Product','product_id');
    }

    public function profit_margin_relations(){
        return $this->hasMany('App\ProductProfitMarginRelation','product_version_id');
    }

    public function material_relations(){
        return $this->hasMany('App\ProductMaterialRelation','product_version_id');
    }
}

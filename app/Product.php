<?php

namespace App;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = ['name','slug','description','is_active','category_id','unit_id'];


    use Sluggable;
    public function sluggable()
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    public function versions()
    {
        return $this->hasMany('App\ProductVersion','product_id','id');
    }

    public function unit(){
        return $this->belongsTo('App\Unit','unit_id');
    }

    public function  category(){
        return $this->belongsTo('App\Category','category_id','id');
    }

    public function quotations(){
        return $this->hasMany('App\QuotationProduct','product_id');
    }
}

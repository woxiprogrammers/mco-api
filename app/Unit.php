<?php

namespace App;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = 'units';

    protected $fillable = ['name','slug','is_active'];

    use Sluggable;
    public function sluggable(){
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    public function products(){
        return $this->hasMany('App\Product','unit_id','id');
    }
}

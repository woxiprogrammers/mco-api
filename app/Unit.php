<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = 'units';

    protected $fillable = ['name','slug','is_active'];

    public function products(){
        return $this->hasMany('App\Product','unit_id','id');
    }
}

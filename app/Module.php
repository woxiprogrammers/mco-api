<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $table = 'modules';

    protected $fillable = ['name','slug','module_id'];

    public function module(){
        return $this->hasOne('App\Module','module_id');
    }
}

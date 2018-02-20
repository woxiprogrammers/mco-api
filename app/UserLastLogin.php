<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserLastLogin extends Model
{
    protected $table = 'user_last_logins';

    protected $fillable = ['user_id','module_id','last_login'];

    public function user(){
        return $this->belongsTo('App\User','user_id');
    }

    public function module(){
        return $this->belongsTo('App\Module','module_id');
    }
}

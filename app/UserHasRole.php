<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserHasRole extends Model
{
    protected $table = 'user_has_roles';

    protected $fillable = ['user_id','role_id'];

    public function user(){
        return $this->belongsTo('App\User','user_id');
    }

    public function role(){
        return $this->belongsTo('App\Role','role_id');
    }
}

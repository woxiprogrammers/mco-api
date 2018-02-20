<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserHasPermission extends Model
{
    protected $table = 'user_has_permissions';

    protected $fillable = ['user_id','permission_id','is_web','is_mobile'];

    public function permission(){
        return $this->belongsTo('App\Permission','permission_id');
    }

    public function user(){
        return $this->belongsTo('App\User','user_id');
    }
}

<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject as JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'users';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name', 'email', 'password','last_name','is_active','mobile','dob','gender','role_id',
        'web_fcm_token','mobile_fcm_token'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
}

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function permissions(){
        return $this->hasMany('App\UserHasPermission','user_id','id');
    }

    public function roles(){
        return $this->hasMany('App\UserHasRole','user_id');
    }

    public function customHasPermission($permission){
        $permissionExists = UserHasPermission::join('permissions','permissions.id','=','user_has_permissions.permission_id')
            ->where('permissions.name','ilike',$permission)
            ->where('user_has_permissions.user_id',$this->id)
            ->where('user_has_permissions.is_mobile', true)
            ->first();
        if($permissionExists  == null){
            return false;
        }else{
            return true;
        }
    }
}

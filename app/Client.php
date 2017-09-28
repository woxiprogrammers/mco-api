<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = 'clients';

    protected $fillable = ['company','address','mobile','email','is_active','gstin'];

    public function project(){
        return $this->hasMany('App\Project','client_id');
    }
}

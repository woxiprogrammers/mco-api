<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'vendors';

    protected $fillable = ['name', 'company', 'mobile', 'email', 'gstin', 'alternate_contact', 'alternate_email','city','is_active'];

    public function cityRelations(){
        return $this->hasMany('App\VendorCityRelation','vendor_id');
    }

    public function materialRelations(){
        return $this->hasMany('App\VendorMaterialRelation','vendor_id');
    }
}
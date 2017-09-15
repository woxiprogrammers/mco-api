<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QuotationMaterial extends Model
{
    protected $table = 'quotation_materials';

    protected $fillable = ['material_id','rate_per_unit','unit_id','is_client_supplied','quotation_id'];

    public function quotation(){
        return $this->belongsTo('App\Quotation','quotation_id');
    }

    public function material(){
        return $this->belongsTo('App\Material','material_id');
    }

    public function unit(){
        return $this->belongsTo('App\Unit','unit_id');
    }
}

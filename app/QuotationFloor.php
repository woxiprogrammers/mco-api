<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QuotationFloor extends Model
{
    protected $table = 'quotation_floors';

    protected $fillable = ['quotation_id','name'];

    public function quotation(){
        return $this->belongsTo('App\Quotation','quotation_id');
    }
}

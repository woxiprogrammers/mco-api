<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QuotationStatus extends Model
{
    protected $table = 'quotation_statuses';

    protected $fillable = ['name','slug'];

    public function quotations(){
        return $this->hasMany('App\Quotation','quotation_status_id');
    }
}

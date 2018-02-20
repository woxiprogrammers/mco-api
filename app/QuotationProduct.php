<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QuotationProduct extends Model
{
    protected $table = 'quotation_products';

    protected $fillable = ['quotation_id','description','product_id','product_version_id','rate_per_unit','quantity','summary_id'];

    public function quotation(){
        return $this->belongsTo('App\Quotation','quotation_id');
    }

    public function product(){
        return $this->belongsTo('App\Product','product_id','id');
    }

    public function quotation_profit_margins(){
        return $this->hasMany('App\QuotationProfitMarginVersion','quotation_product_id');

    }
    public function summary(){
        return $this->belongsTo('App\Summary','summary_id');
    }
}

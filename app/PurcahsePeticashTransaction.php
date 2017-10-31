<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurcahsePeticashTransaction extends Model
{
    protected $table = 'purchase_peticash_transactions';

    protected $fillable = ['name','project_site_id','component_type_id','reference_id','payment_type_id',
                'peticash_transaction_type_id','source_name','quantity','unit_id','bill_number','bill_amount',
                'vehicle_number','in_time','out_time','remark','date','reference_user_id','grn','peticash_status_id',
                'reference_number','admin_remark'
        ];

    public function projectSite(){
        return $this->belongsTo('App\ProjectSite','project_site_id');
    }
    public function componentType(){
        return $this->belongsTo('App\MaterialRequestComponentTypes','component_type_id');
    }
    public function material(){
        return $this->belongsTo('App\Material','reference_id');
    }
    public function asset(){
        return $this->belongsTo('App\Asset','reference_id');
    }
    public function paymentType(){
        return $this->belongsTo('App\PaymentType','payment_type_id');
    }
    public function peticashTransactionType(){
        return $this->belongsTo('App\PeticashTransactionType','peticash_transaction_type_id');
    }
    public function unit(){
        return $this->belongsTo('App\Unit','unit_id');
    }
    public function referenceUser(){
        return $this->belongsTo('App\User','reference_user_id');
    }
    public function peticashStatus(){
        return $this->belongsTo('App\PeticashStatus','peticash_status_id');
    }
}

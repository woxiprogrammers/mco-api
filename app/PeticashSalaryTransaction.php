<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PeticashSalaryTransaction extends Model
{
    protected $table = 'peticash_salary_transactions';

    protected $fillable = ['reference_user_id','employee_id','project_site_id',
        'peticash_transaction_type_id','amount','date','days','peticash_status_id','remark','payable_amount',
        'admin_remark','payment_type_id'];

    public function referenceUser(){
        return $this->belongsTo('App\User','reference_user_id');
    }

    public function projectSite(){
        return $this->belongsTo('App\ProjectSite','project_site_id');
    }

    public function peticashTransactionType(){
        return $this->belongsTo('App\PeticashTransactionType','peticash_transaction_type_id');
    }

    public function peticashStatus(){
        return $this->belongsTo('App\PeticashStatus','peticash_status_id');
    }

    public function paymentType(){
        return $this->belongsTo('App\PaymentType','payment_type_id');
    }
}

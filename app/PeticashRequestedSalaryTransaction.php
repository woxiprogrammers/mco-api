<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PeticashRequestedSalaryTransaction extends Model
{
    protected $table = 'peticash_requested_salary_transactions';

    protected $fillable = ['reference_user_id','employee_id','project_site_id','peticash_transaction_type_id','amount',
        'days','amount','per_day_wages','peticash_status_id'
    ];

    public function employee(){
        return $this->belongsTo('App\Employee','employee_id');
    }

    public function paymentType(){
        return $this->belongsTo('App\PeticashTransactionType','peticash_transaction_type_id');
    }

    public function referenceUser(){
        return $this->belongsTo('App\User','reference_user_id');
    }
    public function projectSite(){
        return $this->belongsTo('App\ProjectSite','project_site_id');
    }
}

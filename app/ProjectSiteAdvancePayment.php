<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectSiteAdvancePayment extends Model
{
    protected $table = 'project_site_advance_payments';

    protected $fillable = ['project_site_id','payment_id','amount','reference_number','bank_id','paid_from_slug'];

    public function paymentType(){
        return $this->belongsTo('App\PaymentType','payment_id');
    }

    public function projectSite(){
        return $this->belongsTo('App\ProjectSite','project_site_id');
    }

    public function bank(){
        return $this->belongsTo('App\BankInfo','bank_id');
    }
}

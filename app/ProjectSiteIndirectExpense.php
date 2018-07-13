<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectSiteIndirectExpense extends Model
{
    protected $table = 'project_site_indirect_expenses';

    protected $fillable = ['project_site_id','gst','tds','paid_from_slug','bank_id','payment_type_id','reference_number'];

    public function projectSite(){
        return $this->belongsTo('App\ProjectSite','project_site_id');
    }

    public function paymentType(){
        return $this->belongsTo('App\PaymentType','payment_type_id');
    }
}

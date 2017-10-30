<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PeticashSiteTransfer extends Model
{
    protected $table = 'peticash_site_transfers';

    protected $fillable = ['user_id','received_from_user_id','amount','date','remark','project_site_id',
                    'payment_id'];

    public function user(){
        return $this->belongsTo('App\User','user_id');
    }

    public function receivedFromUserId(){
        return $this->belongsTo('App\User','received_from_user_id');
    }

    public function projectSite(){
        return $this->belongsTo('App\ProjectSite','project_site_id');
    }

    public function paymentType(){
        return $this->belongsTo('App\PaymentType','payment_id');
    }
}

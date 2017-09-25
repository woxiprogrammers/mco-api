<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequests extends Model
{
    protected $table = 'purchase_requests';

    protected $fillable = ['quotation_id','project_site_id','user_id','behalf_of_user_id','purchase_component_status_id'];
}

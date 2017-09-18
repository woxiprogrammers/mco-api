<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestComponentStatuses extends Model
{
    protected $table = 'purchase_request_component_statuses';

    protected $fillable = ['name','slug'];
}

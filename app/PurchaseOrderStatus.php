<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderStatus extends Model
{
    protected $table = 'purchase_order_statuses';

    protected $fillable = ['name','slug'];

}

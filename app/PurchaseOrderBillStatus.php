<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderBillStatus extends Model
{
    protected $table = 'purchase_order_bill_statuses';

    protected $fillable = ['name','slug'];
}

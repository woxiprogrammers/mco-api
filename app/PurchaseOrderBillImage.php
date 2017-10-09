<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderBillImage extends Model
{
    protected $table = 'purchase_order_bill_images';

    protected $fillable = ['purchase_order_bill_id','name','is_payment_image'];
}

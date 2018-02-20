<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PurchasePeticashTransactionImage extends Model
{
    protected $table = 'purchase_peticash_transaction_images';

    protected $fillable = [
        'purchase_peticash_transaction_id','name','type'
    ];
}

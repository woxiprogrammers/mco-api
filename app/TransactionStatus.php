<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransactionStatus extends Model
{
    protected $table = 'transaction_statuses';

    protected $fillable = ['name', 'slug'];
}

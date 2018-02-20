<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PeticashSalaryTransactionImages extends Model
{
    protected $table = 'peticash_salary_transaction_images';

    protected $fillable = ['name','peticash_salary_transaction_id'];
}

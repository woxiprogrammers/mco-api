<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BankInfo extends Model
{
    protected $table = "bank_info";

    protected $fillable = ['bank_name','account_number','ifs_code','branch_id','branch_name','is_active','balance_amount','total_amount'];


}

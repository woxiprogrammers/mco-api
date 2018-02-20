<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GRNCount extends Model
{
    protected $table = 'grn_counts';

    protected $fillable = ['month','year','count'];
}

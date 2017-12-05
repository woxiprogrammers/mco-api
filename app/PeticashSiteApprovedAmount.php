<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PeticashSiteApprovedAmount extends Model
{
    protected $table = 'peticash_site_approved_amounts';

    protected $fillable = ['project_site_id','salary_amount_approved'];
}

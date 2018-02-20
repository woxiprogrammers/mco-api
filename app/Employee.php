<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employees';

    protected $fillable = ['name','mobile','per_day_wages','project_site_id','labour_id','is_active','employee_type_id','gender',
                            'address','pan_card','aadhaar_card','employee_id','designation','joining_date','termination_date','bank_account_number',
                            'bank_name','branch_id','account_holder_name','branch_name','ifs_code'
        ];

    public function projectSite(){
        return $this->belongsTo('App\ProjectSite','project_site_id');
    }
}

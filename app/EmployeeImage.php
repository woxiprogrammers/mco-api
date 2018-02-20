<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmployeeImage extends Model
{
    protected $table = 'employee_images';

    protected $fillable = ['employee_id','employee_image_type_id','name'];
}

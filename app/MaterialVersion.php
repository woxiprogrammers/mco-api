<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MaterialVersion extends Model
{
    protected $table = 'material_versions';

    protected $fillable = ['material_id','rate_per_unit','unit_id'];

    public function material(){
        $this->belongsTo('App\Material','material_id');
    }
}

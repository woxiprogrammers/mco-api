<?php

namespace App;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $table = 'materials';

    protected $fillable = ['name','slug','is_active','created_at','updated_at','rate_per_unit','unit_id','gst','hsn_code'];

    use Sluggable;
    public function sluggable()
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    public function versions()
    {
        return $this->hasMany('App\MaterialVersion','material_version');
    }
}

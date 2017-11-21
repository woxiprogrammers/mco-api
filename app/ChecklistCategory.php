<?php

namespace App;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class ChecklistCategory extends Model
{
    protected $table = 'checklist_categories';

    protected $fillable = ['name','slug','is_active','category_id'];

    use Sluggable;
    public function sluggable()
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }
}

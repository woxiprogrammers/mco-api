<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectSiteUserCheckpointImage extends Model
{
    protected $table = 'project_site_user_checkpoint_images';

    protected $fillable = ['project_site_user_checkpoint_id','project_site_checklist_checkpoint_image_id','image'];

    public function projectSiteUserCheckpoint(){
        return $this->belongsTo('App\ProjectSiteUserCheckpoint','project_site_user_checkpoint_id');
    }
}

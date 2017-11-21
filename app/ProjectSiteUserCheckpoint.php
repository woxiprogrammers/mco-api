<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectSiteUserCheckpoint extends Model
{
    protected $table = 'project_site_user_checkpoints';

    protected $fillable = ['project_site_checklist_checkpoint_id','project_site_user_checkpoint_id','project_site_user_checklist_assignment_id','remark','is_ok'];
}

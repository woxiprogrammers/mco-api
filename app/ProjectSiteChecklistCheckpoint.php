<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectSiteChecklistCheckpoint extends Model
{
    protected $table = 'project_site_checklist_checkpoints';

    protected $fillable = ['project_site_checklist_id','description','is_remark_required'];

    public function projectSiteChecklist(){
        return $this->belongsTo('App\ProjectSiteChecklist','project_site_checklist_id');
    }


}

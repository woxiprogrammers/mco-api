<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectSiteChecklistCheckpoint extends Model
{
    protected $table = 'project_site_checklist_checkpoints';

    protected $fillable = ['project_site_checklist_id','checklist_category_id','description','is_remark_required'];

    public function projectSiteChecklist(){
        return $this->belongsTo('App\ProjectSiteChecklist','project_site_checklist_id');
    }

    public function checklistCategory(){
        return $this->belongsTo('App\ChecklistCategory','checklist_category_id');
    }
}

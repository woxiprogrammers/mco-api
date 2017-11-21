<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectSiteUserChecklistAssignment extends Model
{
    protected $table = 'project_site_user_checklist_assignments';

    protected $fillable = ['project_site_checklist_id','checklist_status_id','assigned_to','assigned_by','reviewed_by','project_site_user_checklist_assignment_id'];

    public function projectSiteChecklist(){
        return $this->belongsTo('App\ProjectSiteChecklist','project_site_checklist_id');
    }

    public function checklistStatus(){
        return $this->belongsTo('App\ChecklistStatus','checklist_status_id');
    }

    public function assignedToUser(){
        return $this->belongsTo('App\User','assigned_to');
    }

    public function reviewedByUser(){
        return $this->belongsTo('App\User','reviewed_by');
    }

    public function assignedByUser(){
        return $this->belongsTo('App\User','assigned_by');
    }
}

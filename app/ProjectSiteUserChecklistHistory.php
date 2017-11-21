<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProjectSiteUserChecklistHistory extends Model
{
    protected $table = 'project_site_user_checklist_history_table';

    protected $fillable = ['checklist_status_id','project_site_user_checklist_assignment_id'];

    public function checklistStatus(){
        return $this->belongsTo('App\ChecklistStatus','checklist_status_id');
    }
}

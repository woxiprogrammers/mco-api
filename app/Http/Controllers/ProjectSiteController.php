<?php

namespace App\Http\Controllers;
use App\ProjectSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class ProjectSiteController extends BaseController{

    public function getAllProjectSites(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $data = ProjectSite::join('projects','project_sites.project_id','=','projects.id')
                ->join('clients','clients.id','=','projects.client_id')
                ->where('projects.is_active',true)
                ->select('project_sites.id as project_site_id','project_sites.name as project_site_name','projects.id as project_id','projects.name as project_name','clients.id as client_id','clients.company as client_company')
                ->orderBy('project_name','asc')
                ->get()->toArray();
        }catch(\Exception $e){
            $message = $e->getMessage();
            $status = 500;
            $data = [
                'action' => 'Get System Project Sites',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
            'data' => $data
        ];
        return response()->json($response,$status);
    }
}
<?php

namespace App\Http\Controllers\DPR;

use App\DprDetail;
use App\Subcontractor;
use App\SubcontractorDPRCategoryRelation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class DprController extends BaseController{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        if (!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function subcontractorListing(Request $request){
        try{
            $status = 200;
            $response = Subcontractor::where('is_active', true)->select('id','company_name as name')->get()->toArray();
        }catch(\Exception $e){
            $data = [
                'action' => 'Subcontractor listing in DPR',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $status = 500;
            $response = [
                'message' => 'Something went wrong.'
            ];
        }
        return response()->json($response,$status);
    }

    public function categoryListing(Request $request){
        try{
            $status = 200;
            $response = SubcontractorDPRCategoryRelation::join('dpr_main_categories','dpr_main_categories.id','=','subcontractor_dpr_category_relations.dpr_main_category_id')
                                        ->where('subcontractor_dpr_category_relations.subcontractor_id',$request->subcontractor_id)
                                        ->select('dpr_main_categories.id as id','dpr_main_categories.name as name')
                                        ->get()
                                        ->toArray();
        }catch(\Exception $e){
            $data = [
                'action' => 'Category listing in DPR',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $status = 500;
            $response = [
                'message' => 'Something went wrong.'
            ];
        }
        return response()->json($response,$status);
    }

    public function saveDetails(Request $request){
        try{
            $status = 200;
            $response = [
                'message' => 'Data saved successfully'
            ];
            $dprDetailData = [
                'project_site_id' => $request->project_site_id
            ];
            foreach($request->number_of_users as $dprCategoryId => $numberOfUser){
                $subcontractorDprCategoryRelationId = SubcontractorDPRCategoryRelation::where('subcontractor_id',$request->subcontractor_id)
                                                                ->where('dpr_main_category_id', $dprCategoryId)
                                                                ->pluck('id')
                                                                ->first();
                if($subcontractorDprCategoryRelationId != null){
                    $dprDetailData['number_of_users'] = $numberOfUser;
                    $dprDetailData['subcontractor_dpr_category_relation_id'] = $subcontractorDprCategoryRelationId;
                    DprDetail::create($dprDetailData);
                }
            }
        }catch(\Exception $e){
            $data = [
                'action' => 'Category listing in DPR',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            $status = 500;
            $response = [
                'message' => 'Something went wrong.'
            ];
        }
        return response()->json($response,$status);
    }
}


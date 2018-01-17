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
            $data = Subcontractor::where('is_active', true)->select('id','company_name as name')->get()->toArray();
            $response = [
                'data' => $data,
                'message' => 'Subcontractors listed successfully !'
            ];
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
            $response = array();
            $response['data'] = SubcontractorDPRCategoryRelation::join('dpr_main_categories','dpr_main_categories.id','=','subcontractor_dpr_category_relations.dpr_main_category_id')
                                        ->where('subcontractor_dpr_category_relations.subcontractor_id',$request->subcontractor_id)
                                        ->select('dpr_main_categories.id as id','dpr_main_categories.name as name')
                                        ->get()
                                        ->toArray();
            $response['message'] = 'Subcontractor\'s categories listed successfully.!';
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

    public function dprDetailsListing(Request $request){
        try{
//            dd($request->all());
            $status = 200;
            $response = array();
            $dprDetails = DprDetail::join('subcontractor_dpr_category_relations','subcontractor_dpr_category_relations.id','=','dpr_details.subcontractor_dpr_category_relation_id')
                                ->join('subcontractor','subcontractor.id','=','subcontractor_dpr_category_relations.subcontractor_id')
                                ->join('dpr_main_categories','dpr_main_categories.id','=','subcontractor_dpr_category_relations.dpr_main_category_id')
                                ->where('dpr_details.project_site_id', $request->project_site_id)
                                ->whereDate('dpr_details.created_at', $request->date)
                                ->select('dpr_details.id as dpr_detail_id','subcontractor.id as subcontractor_id','subcontractor.company_name as subcontractor_name','subcontractor_dpr_category_relations.id as subcontractor_dpr_category_relation_id','dpr_main_categories.name as dpr_main_category_name','dpr_details.number_of_users as number_of_users')
                                ->orderBy('dpr_detail_id','desc')
                                ->get();
            $subContractorData = array();
            foreach($dprDetails as $dprDetail){
                if(array_key_exists($dprDetail['subcontractor_id'],$subContractorData)){
                    $subContractorData[$dprDetail['subcontractor_id']]['users'][] = [
                        'id' => $dprDetail['subcontractor_dpr_category_relation_id'],
                        'cat' => $dprDetail['dpr_main_category_name'],
                        'no_of_users' => $dprDetail['number_of_users']
                    ];
                }else{
                    $subContractorData[$dprDetail['subcontractor_id']] = [
                        'id' => $dprDetail['subcontractor_id'],
                        'name' => $dprDetail['subcontractor_name'],
                        'users' => array()
                    ];
                    $subContractorData[$dprDetail['subcontractor_id']]['users'][] = [
                        'id' => $dprDetail['subcontractor_dpr_category_relation_id'],
                        'cat' => $dprDetail['dpr_main_category_name'],
                        'no_of_users' => $dprDetail['number_of_users']
                    ];
                }
            }
            $response = [
                'message' => 'DPR listed successfully !',
                'data' => [
                    'sub_contractor_list' => array_values($subContractorData)
                ]
            ];
        }catch(\Exception $e){
            $data = [
                'action' =>'Get Dpr Details Listing',
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


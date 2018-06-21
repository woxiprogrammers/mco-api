<?php

namespace App\Http\Controllers\DPR;

use App\DprDetail;
use App\DprDetailImageRelation;
use App\DprImage;
use App\Subcontractor;
use App\SubcontractorDPRCategoryRelation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
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
            $projectSiteId = $request->project_site_id;
            $user = Auth::user();
            $dprDetailIds = array();
            foreach($request->number_of_users as $userData){
                $subcontractorDprCategoryRelationId = SubcontractorDPRCategoryRelation::where('subcontractor_id',$request->subcontractor_id)
                                                                ->where('dpr_main_category_id', $userData['category_id'])
                                                                ->pluck('id')
                                                                ->first();
                $today = Carbon::now();
                if($subcontractorDprCategoryRelationId != null){
                    $dprDetail = DprDetail::where('subcontractor_dpr_category_relation_id', $subcontractorDprCategoryRelationId)->whereDate('created_at', $today)->first();
                    if($dprDetail != null){
                        $dprDetail->update(['number_of_users' => $userData['users']]);
                    }else{
                        $dprDetailData = [
                            'project_site_id' => $request->project_site_id,
                            'number_of_users' => $userData['users'],
                            'subcontractor_dpr_category_relation_id' => $subcontractorDprCategoryRelationId
                        ];
                        $dprDetail = DprDetail::create($dprDetailData);
                    }
                    $dprDetailIds[] = $dprDetail->id;
                }
            }
            if($request->has('images')){
                $userDirectory = sha1($user->id);
                $projectSiteDirectory = sha1($projectSiteId);
                $tempImageUploadPath = env('WEB_PUBLIC_PATH').env('DPR_TEMP_UPLOAD').DIRECTORY_SEPARATOR.$userDirectory;
                $imageUploadPath = env('WEB_PUBLIC_PATH').env('DPR_UPLOAD').DIRECTORY_SEPARATOR.$projectSiteDirectory;
                foreach($request->images as $image){
                    $imageName = basename($image);
                    $newTempImageUploadPath = $tempImageUploadPath.'/'.$imageName;
                    $dprImageData = [
                        'name'=> $imageName
                    ];
                    if (!file_exists($imageUploadPath)) {
                        File::makeDirectory($imageUploadPath, $mode = 0777, true, true);
                    }
                    if(File::exists($newTempImageUploadPath)){
                        $imageUploadNewPath = $imageUploadPath.DIRECTORY_SEPARATOR.$imageName;
                        File::move($newTempImageUploadPath,$imageUploadNewPath);
                    }
                    $dprImage = DprImage::create($dprImageData);
                    foreach($dprDetailIds as $dprDetailId){
                        $dprDetailImageRelationData = [
                            'dpr_detail_id' => $dprDetailId,
                            'dpr_image_id' => $dprImage->id
                        ];
                        $dprDetailImageRelation = DprDetailImageRelation::create($dprDetailImageRelationData);
                    }
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
            $status = 200;
            $dprDetails = DprDetail::join('subcontractor_dpr_category_relations','subcontractor_dpr_category_relations.id','=','dpr_details.subcontractor_dpr_category_relation_id')
                                ->join('subcontractor','subcontractor.id','=','subcontractor_dpr_category_relations.subcontractor_id')
                                ->join('dpr_main_categories','dpr_main_categories.id','=','subcontractor_dpr_category_relations.dpr_main_category_id')
                                ->where('dpr_details.project_site_id', $request->project_site_id)
                                ->whereDate('dpr_details.created_at', $request->date)
                                ->select('dpr_details.id as dpr_detail_id','subcontractor.id as subcontractor_id','subcontractor.company_name as subcontractor_name','subcontractor_dpr_category_relations.id as subcontractor_dpr_category_relation_id','dpr_main_categories.name as dpr_main_category_name','dpr_details.number_of_users as number_of_users','dpr_details.project_site_id as project_site_id')
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
            $dprImageData = DprDetailImageRelation::join('dpr_images','dpr_images.id','=','dpr_detail_image_relations.dpr_image_id')
                ->where('dpr_detail_image_relations.dpr_detail_id', $dprDetails[0]['dpr_detail_id'])
                ->select('dpr_images.name as image_name','dpr_detail_image_relations.id as dpr_detail_image_relation_id','dpr_images.id as dpr_image_id')
                ->get();
            $subcontractorCategoryImages = array();
            if(count($dprImageData) > 0){
                $projectSiteDirectory = sha1($dprDetails[0]['project_site_id']);
                $imageUploadPath = env('DPR_UPLOAD').DIRECTORY_SEPARATOR.$projectSiteDirectory;
                foreach($dprImageData as $dprImageDatum){
                    $imagePath = $imageUploadPath.DIRECTORY_SEPARATOR.$dprImageDatum['image_name'];
                    if (file_exists(env('WEB_PUBLIC_PATH').$imagePath)) {
                        if(!array_key_exists($dprImageDatum['dpr_image_id'], $subcontractorCategoryImages))
                            $subcontractorCategoryImages[$dprImageDatum['dpr_image_id']] = [
                                'path' => $imagePath,
                                'dpr_image_id' => $dprImageDatum['dpr_image_id']
                            ];
                    }
                }
            }
            $response = [
                'message' => 'DPR listed successfully !',
                'data' => [
                    'sub_contractor_list' => array_values($subContractorData),
                    'images' => array_values($subcontractorCategoryImages)
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


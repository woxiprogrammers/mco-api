<?php
    /**
     * Created by Shubham.
     * Date: 29/11/17
     * Time: 11:35 AM
     */
    namespace App\Http\Controllers\Drawing;
    use App\DrawingCategory;
    use App\DrawingCategorySiteRelation;
    use App\DrawingImage;
    use App\DrawingImageComment;
    use App\DrawingImageVersion;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Log;
    use Laravel\Lumen\Routing\Controller as BaseController;

    class DrawingController extends BaseController
    {
        public function __construct()
        {
            $this->middleware('jwt.auth', ['except' => ['autoSuggest', 'getPurchaseRequestComponentStatus']]);
            if (!Auth::guest()) {
                $this->user = Auth::user();
            }
        }
        public function getMainCategories(Request $request){
            try{
                 $message = "success";
                 $status = 200;
                 $subCategories = DrawingCategorySiteRelation::where('project_site_id',$request->project_site_id)->pluck('drawing_category_id')->toArray();
                 $drawing_sub_categories = DrawingCategory::whereIn('id',$subCategories)->pluck('drawing_category_id')->toArray();
                 $main_categories = DrawingCategory::whereIn('id',$drawing_sub_categories)->select('id','name')->get()->toArray();
                 $data=[
                   'main_categories'=>$main_categories
                 ];
            }catch (\Exception $e){
                $status = 500;
                $message = "Fail";
                $data = [
                    'action' => 'Get Main Categories',
                    'params' => $request->all(),
                    'exception' => $e->getMessage()
                ];
                $page_id = "";
                Log::critical(json_encode($data));
            }
            $response = [
                "data" => $data,
                "message" => $message,

            ];
            return response()->json($response,$status);
        }
        public function getSubCategories(Request $request){
            try{
                $pageId = $request->page;
                $sub_categories = DrawingCategory::where('drawing_category_id',$request->main_category_id)->select('id','name')->get();
                $status = 200;
                $message = "Success";
                $displayLength = 30;
                $start = ((int)$pageId) * $displayLength;
                $totalSent = ($pageId + 1) * $displayLength;
                $totalMainCategoriesCount = count($sub_categories);
                $remainingCount = $totalMainCategoriesCount - $totalSent;
                $data['sub_categories'] = array();
                for($iterator = $start,$jIterator = 0; $iterator < $totalSent && $jIterator < $totalMainCategoriesCount; $iterator++,$jIterator++){
                    $data['sub_categories'][] = $sub_categories[$iterator];
                }
                if($remainingCount > 0 ){
                    $page_id = (string)($pageId + 1);
                }else{
                    $page_id = "";
                }
            }catch (\Exception $e){
                $status = 500;
                $message = "Fail";
                $data = [
                    'action' => 'Get Main Categories',
                    'params' => $request->all(),
                    'exception' => $e->getMessage()
                ];
                $page_id = "";
                Log::critical(json_encode($data));
            }
            $response = [
                "data" => $data,
                "page_id" => $pageId,
                "message" => $message,

            ];
            return response()->json($response,$status);
        }
        public function getCurrentVersionImages(Request $request){
            try{
                $pageId = $request->page;
                $drawing_category_site_relation_id = DrawingCategorySiteRelation::where('drawing_category_id',$request->sub_category_id)
                    ->where('project_site_id',$request->project_site_id)
                    ->pluck('id')->toArray();
                $drawing_images_id = DrawingImage::whereIn('drawing_category_site_relation_id',$drawing_category_site_relation_id)->pluck('id')->toArray();
                $iterator = 0;
                $drawing_image_latest_version = array();
                $path = env('DRAWING_IMAGE_UPLOAD_PATH').DIRECTORY_SEPARATOR.sha1($request->project_site_id).DIRECTORY_SEPARATOR.sha1($request->sub_category_id);
                foreach ($drawing_images_id as $value){
                    $drawing_image_latest_version[$iterator] =DrawingImageVersion:: where('drawing_image_id',$value)->orderBy('id','desc')->select('id','title','name')->first()->toArray();
                    $drawing_image_latest_version[$iterator]['encoded_name'] = $path.DIRECTORY_SEPARATOR.urlencode($drawing_image_latest_version[$iterator]['name']);
                    $iterator++;
                }
                $status = 200;
                $message = "Success";
                $displayLength = 30;
                $start = ((int)$pageId) * $displayLength;
                $totalSent = ($pageId + 1) * $displayLength;
                $totalMainCategoriesCount = count($drawing_image_latest_version);
                $remainingCount = $totalMainCategoriesCount - $totalSent;
                $data['images'] = array();
                for($iterator = $start,$jIterator = 0; $iterator < $totalSent && $jIterator < $totalMainCategoriesCount; $iterator++,$jIterator++){
                    $data['images'][] = $drawing_image_latest_version[$iterator];
                }
                if($remainingCount > 0 ){
                    $page_id = (string)($pageId + 1);
                }else{
                    $page_id = "";
                }
            }catch (\Exception $e){
                $status = 500;
                $message = "Fail";
                $data = [
                    'action' => 'Get current versions',
                    'params' => $request->all(),
                    'exception' => $e->getMessage()
                ];
                $page_id = "";
                Log::critical(json_encode($data));
            }
            $response = [
                "data" => $data,
                "page_id" => $pageId,
                "message" => $message,

            ];
            return response()->json($response,$status);
        }
        public function addComment(Request $request){
            try{
                $message = "success";
                $status = 200;
                $imageData['comment'] = $request->comment;
                $imageData['drawing_image_version_id'] = $request->drawing_image_version_id;
                $query = DrawingImageComment::create($imageData);

            }catch (\Exception $e){
                $status = 500;
                $message = "Fail";
                $data = [
                    'action' => 'Get Main Categories',
                    'params' => $request->all(),
                    'exception' => $e->getMessage()
                ];
                $page_id = "";
                Log::critical(json_encode($data));
            }
            $response = [

                "message" => $message,

            ];
            return response()->json($response,$status);
        }
        public function getComments(Request $request){
            try{
                $message = "success";
                $status = 200;
                $comments = DrawingImageComment::where('drawing_image_version_id',$request->drawing_image_version_id)->select('id','comment')->get()->toArray();
                $data=[
                    'comments'=>$comments
                ];

            }catch (\Exception $e){
                $status = 500;
                $message = "Fail";
                $data = [
                    'action' => 'Get Comments',
                    'params' => $request->all(),
                    'exception' => $e->getMessage()
                ];
                $page_id = "";
                Log::critical(json_encode($data));
            }
            $response = [
                "data" => $data,
                "message" => $message,
            ];
            return response()->json($response,$status);
        }
        public function getAllImageVersions(Request $request){
            try{
                $message = "success";
                $status = 200;
                $image_id = DrawingImageVersion::where('id',$request->image_id)->pluck('drawing_image_id')->first();
                $versions = DrawingImageVersion::where('drawing_image_id',$image_id)->select('id','title','name')->get();
                $path = env('DRAWING_IMAGE_UPLOAD_PATH').DIRECTORY_SEPARATOR.sha1($request->project_site_id).DIRECTORY_SEPARATOR.sha1($request->sub_category_id);
                $data=[
                    'versions'=>$versions,
                    'path' => $path
                ];

            }catch (\Exception $e){
                $status = 500;
                $message = "Fail";
                $data = [
                    'action' => 'Get Comments',
                    'params' => $request->all(),
                    'exception' => $e->getMessage()
                ];
                $page_id = "";
                Log::critical(json_encode($data));
            }
            $response = [
                "data" => $data,
                "message" => $message,
            ];
            return response()->json($response,$status);
        }

  }
<?php
    /**
     * Created by Shubham.
     * Date: 29/11/17
     * Time: 11:35 AM
     */
    namespace App\Http\Controllers\Drawing;
    use App\DrawingCategory;
    use App\DrawingCategorySiteRelation;
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

  }
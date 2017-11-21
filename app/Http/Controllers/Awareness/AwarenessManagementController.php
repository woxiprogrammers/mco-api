<?php
    /**
     * Created by Shubham.
     * User: sagar
     * Date: 21/11/17
     * Time: 11:35 AM
     */
    namespace App\Http\Controllers\Awareness;
    use App\AwarenessMainCategory;
    use App\AwarenessSubCategory;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Log;
    use Laravel\Lumen\Routing\Controller as BaseController;
    use Mockery\Exception;

    class AwarenessManagementController extends BaseController
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
                $pageId = $request->page;
                $main_categories = AwarenessMainCategory::select('id','name')->get();
                $status = 200;
                $message = "Success";
                $displayLength = 30;
                $start = ((int)$pageId) * $displayLength;
                $totalSent = ($pageId + 1) * $displayLength;
                $totalMainCategoriesCount = count($main_categories);
                $remainingCount = $totalMainCategoriesCount - $totalSent;
                $data['main_categories'] = array();
                for($iterator = $start,$jIterator = 0; $iterator < $totalSent && $jIterator < $totalMainCategoriesCount; $iterator++,$jIterator++){
                    $data['main_categories'][] = $main_categories[$iterator];
                }
                if($remainingCount > 0 ){
                    $page_id = (string)($pageId + 1);
                }else{
                    $page_id = "";
                }
            }catch (Exception $e){
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
        public function getSubCategories(Request $request){
            try{
                $pageId = $request->page;
                $sub_categories = AwarenessSubCategory::where('awareness_main_category_id',$request->main_category_id)->select('id','name')->get();
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
            }catch (Exception $e){
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
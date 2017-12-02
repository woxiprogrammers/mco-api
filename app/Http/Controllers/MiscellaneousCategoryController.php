<?php
    /**
     * Created by Harsha.
     * Date: 2/12/17
     * Time: 3:54 PM
     */

namespace App\Http\Controllers;
use App\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;


class MiscellaneousCategoryController extends BaseController{

    public function getAllMiscellaneousCategory(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $data = Category::where('is_miscellaneous',true)->where('is_active',true)->select('id as category_id','name as category_name')->get();
        }catch(\Exception $e){
            $message = $e->getMessage();
            $status = 500;
            $data = [
                'action' => 'Get System Miscellaneous Category',
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
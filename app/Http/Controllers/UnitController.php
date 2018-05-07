<?php
    /**
     * Created by Harsha.
     * Date: 1/11/17
     * Time: 10:52 AM
     */

namespace App\Http\Controllers;
use App\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class UnitController extends BaseController{

    public function getAllSystemUnits(Request $request){
        try{
            $message = 'Success';
            $status = 200;
            $data = Unit::select('id','name','slug','is_active')->orderBy('name','asc')->get();
        }catch(\Exception $e){
            $message = $e->getMessage();
            $status = 500;
            $data = [
                'action' => 'Get all System Units',
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
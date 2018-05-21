<?php
    /**
     * Created by PhpStorm.
     * User: manoj
     * Date: 10/5/18
     * Time: 12:11 PM
     */

namespace App\Http\Controllers;

use App\BankInfo;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;


class BankController extends BaseController{

    public function getAllBanks(Request $request){
        try{
            $banks = BankInfo::select('id as bank_id','bank_name','balance_amount','account_number')->get();
            $message = "Success";
            $status = 200;
            $data['banks'] = $banks;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get all banks',
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
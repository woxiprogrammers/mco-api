<?php
    /**
     * Created by Harsha.
     * User: manoj
     * Date: 22/9/17
     * Time: 10:14 AM
     */

namespace App\Http\Controllers\Purchase;
use App\PurchaseRequestComponentStatuses;
use App\PurchaseRequests;
use App\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Mockery\Exception;

class PurchaseRequestController extends BaseController{

    public function construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()){
            $this->user = Auth::user();
        }
    }

    public function createPurchaseRequest(Request $request){
        try{
            $status = 200;
            $message = "Success";
            $user = Auth::user();
            $requestData = $request->all();
            if($request->has('item_list')){
                dd($request['item_list']);
            }
            $purchaseRequest = array();
            $alreadyCreatedPurchaseRequest = PurchaseRequests::where('project_site_id',$requestData['project_site_id'])->where('user_id',$user['id'])->first();
            if(count($alreadyCreatedPurchaseRequest) > 0){
                $purchaseRequest = $alreadyCreatedPurchaseRequest;
            }else{
                $quotationId = Quotation::where('project_site_id',$requestData['project_site_id'])->pluck('id')->first();
                if(count($quotationId) > 0){
                    $purchaseRequest['quotation_id'] = $quotationId;
                }
                $purchaseRequest['user'] = $purchaseRequest['behalf_of_user_id'] = $user['id'];
                //$purchaseRequestedStatus = PurchaseRequestComponentStatuses::where('slug','')

            }


        }catch (Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Create Material Request',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message
        ];
        return response()->json($response,$status);
    }
}
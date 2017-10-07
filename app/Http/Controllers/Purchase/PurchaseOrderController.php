<?php
    /**
     * Created by Harsha.
     * Date: 5/10/17
     * Time: 11:18 AM
     */

namespace App\Http\Controllers\Purchase;

use App\MaterialRequestComponents;
use App\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class PurchaseOrderController extends BaseController{

    public function __construct(){
        $this->middleware('jwt.auth');
        if(!Auth::guest()){
            $this->user = Auth::user();
        }
    }

    public function getPurchaseOrderListing(Request $request){
        try{
            $purchaseOrderDetail = PurchaseOrder::where('purchase_request_id',$request['purchase_request_id'])->get();
            $purchaseOrderList = array();
            $iterator = 0;
            if(count($purchaseOrderDetail) > 0){
                foreach($purchaseOrderDetail as $key => $purchaseOrder){
                    $purchaseOrderList[$iterator]['purchase_order_id'] = $purchaseOrder['id'];
                    $projectSite = $purchaseOrder->purchaseRequest->projectSite;
                    $purchaseOrderList[$iterator]['purchase_order_format_id'] = $this->getPurchaseOrderIDFormat($projectSite['id'],$purchaseOrder['created_at'],$iterator);
                    $purchaseOrderList[$iterator]['purchase_request_id'] = $purchaseOrder['purchase_request_id'];
                    $project = $projectSite->project;
                    $purchaseOrderList[$iterator]['vendor_id'] = $purchaseOrder['vendor_id'];
                    $purchaseOrderList[$iterator]['vendor_name'] = $purchaseOrder->vendor->name;
                    $purchaseOrderList[$iterator]['client_name'] = $project->client->company;
                    $purchaseOrderList[$iterator]['project'] = $project->name;
                    $purchaseOrderList[$iterator]['date'] = date($purchaseOrder['created_at']);
                    $purchaseRequestComponentIds = $purchaseOrder->purchaseOrderComponent->pluck('purchase_request_component_id');
                    $material_names = MaterialRequestComponents::join('purchase_request_components','material_request_components.id','=','purchase_request_components.material_request_component_id')
                        ->whereIn('purchase_request_components.id',$purchaseRequestComponentIds)
                        ->distinct('material_request_components.name')->select('material_request_components.name')->take(5)->get();
                    $purchaseOrderList[$iterator]['materials'] = $material_names->implode('name', ', ');
                    $purchaseOrderList[$iterator]['status'] = ($purchaseOrder['is_approved'] == true) ? 'Approved' : 'Disapproved';
                    $iterator++;
                }
            }
            $data['purchase_order_list'] = $purchaseOrderList;
            $message = "Success";
            $status = 200;

        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Purchase Order Listing',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'data' => $data,
            'message' => $message
        ];
        return response()->json($response,$status);
    }

    public function getPurchaseOrderIDFormat($project_site_id,$created_at,$serial_no){
        $format = "PO".$project_site_id.date_format($created_at,'y').date_format($created_at,'m').date_format($created_at,'d').$serial_no;
        return $format;
    }
}
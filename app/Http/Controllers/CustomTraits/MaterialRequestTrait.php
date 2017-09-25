<?php


namespace App\Http\Controllers\CustomTraits;

use App\MaterialRequestComponents;
use App\MaterialRequests;
use App\PurchaseRequestComponentStatuses;
use App\Quotation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait MaterialRequestTrait{

    public function createMaterialRequest($data,$user,$is_purchase_request){
        $quotationId = Quotation::where('project_site_id',$data['project_site_id'])->pluck('id')->first();
        $alreadyCreatedMaterialRequest = MaterialRequests::where('project_site_id',$data['project_site_id'])->where('user_id',$user['id'])->first();
        if(count($alreadyCreatedMaterialRequest) > 0){
            $materialRequest = $alreadyCreatedMaterialRequest;
        }else{
            $materialRequest['project_site_id'] = $data['project_site_id'];
            $materialRequest['user_id'] = $user['id'];
            $materialRequest['quotation_id'] = $quotationId != null ? $quotationId['id'] : null;
            $materialRequest['assigned_to'] = $data['assigned_to'];
            $materialRequest = MaterialRequests::create($materialRequest);
        }
        foreach($data['item_list'] as $key => $itemData){
            $materialRequestComponentData['material_request_id'] = $materialRequest['id'];
            $materialRequestComponentData['name'] = $itemData['name'];
            $materialRequestComponentData['quantity'] = $itemData['quantity'];
            $materialRequestComponentData['unit_id'] = $itemData['unit_id'];
            $materialRequestComponentData['component_type_id'] = $itemData['component_type_id'];
            if($is_purchase_request == true){
                $materialRequestComponentData['component_status_id'] = PurchaseRequestComponentStatuses::where('slug','p-r-assigned')->pluck('id')->first();
            }else{
                $materialRequestComponentData['component_status_id'] = PurchaseRequestComponentStatuses::where('slug','pending')->pluck('id')->first();
            }
            $materialRequestComponentData['created_at'] = Carbon::now();
            $materialRequestComponentData['updated_at'] = Carbon::now();
            $materialRequestComponent[] = MaterialRequestComponents::insertGetId($materialRequestComponentData);
            if(array_has($itemData,'images')){
                //images goes here
            }

        }
        return $materialRequestComponent;
    }
}
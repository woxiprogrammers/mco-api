<?php

namespace App\Http\Controllers\CustomTraits;

use App\InventoryComponentTransfers;
use App\InventoryTransferTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait InventoryTrait{
    public function createInventoryTransfer(Request $request){
        try{
            $inventoryComponentTransferData = $request->except('name','type','token');
            $selectedTransferType = InventoryTransferTypes::where('slug',$request['name'])->where('type',$request['type'])->first();
            $inventoryComponentTransferData['transfer_type_id'] = $selectedTransferType->id;
            InventoryComponentTransfers::create($inventoryComponentTransferData);
            $message = "Inventory Component moved ".strtolower($selectedTransferType->type)." successfully";
            $status = 200;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Create Transfer for inventory',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
        ];
        return response()->json($response,$status);
    }
}
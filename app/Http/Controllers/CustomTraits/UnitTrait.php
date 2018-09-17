<?php

namespace App\Http\Controllers\CustomTraits;


use App\UnitConversion;
use Illuminate\Support\Facades\Log;

trait UnitTrait{
    public function unitConversion($unit_from_id,$unit_to_id,$quantity_from){
        $unitConversionData = UnitConversion::where('unit_1_id',$unit_from_id)->where('unit_2_id',$unit_to_id)->first();
        if($unitConversionData == null){
            $data['error_message'] = true;
        }elseif(count($unitConversionData) > 0){
            $data['quantity_to'] = ($quantity_from == null) ? null :($unitConversionData['unit_2_value'] * $quantity_from) / $unitConversionData['unit_1_value'];
            $data['unit_to_id'] = $unitConversionData->unit_2_id;
            $data['unit_to_name'] = $unitConversionData->toUnit->name;
            $data['error_message'] = false;
        }else{
            $reverseUnitConversionData = UnitConversion::where('unit_2_id',$unit_from_id)->where('unit_1_id',$unit_to_id)->first();
            $data['quantity_to'] = ($quantity_from == null) ? null :($reverseUnitConversionData['unit_1_value'] * $quantity_from) / $reverseUnitConversionData['unit_2_value'];
            $data['unit_to_id'] = $reverseUnitConversionData->unit_1_id;
            $data['unit_to_name'] = $reverseUnitConversionData->fromUnit->name;
            $data['error_message'] = false;
        }
        return $data;
    }
}
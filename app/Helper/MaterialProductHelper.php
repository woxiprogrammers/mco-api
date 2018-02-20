<?php
    /**
     * Created by Ameya Joshi.
     * Date: 29/6/17
     * Time: 2:44 PM
     */

namespace App\Helper;


use Illuminate\Support\Facades\Log;

class MaterialProductHelper{

    static function customRound($number,$precision = null){
        try{
            $responseNumber = (int)$number/1;
            $remainder = fmod($number,1);
            if(abs($remainder) >= 0.5){
                if($responseNumber >= 0 ){
                    $responseNumber += 0.5;
                }else{
                    $responseNumber -= 0.5;
                }
            }
        }catch(\Exception $e){
            $data = [
                'action' => 'Custom Round function',
                'number' => $number,
                'exception' => $e->getMessage()
            ];
            Log::critical (json_encode($data));
            $responseNumber = null;
        }
        return $responseNumber;
    }
}
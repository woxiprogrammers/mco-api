<?php
    /**
     * Created by Harsha.
     * Date: 7/10/17
     * Time: 11:16 AM
     */

namespace App\Http\Controllers\CustomTraits;


trait PurchaseTrait{

    public function getPurchaseIDFormat($slug,$project_site_id,$created_at,$serial_no){
        switch ($slug){
            case 'material-request' :
                $format = "MR".$project_site_id.date_format($created_at,'y').date_format($created_at,'m').date_format($created_at,'d').$serial_no;
            break;

            case 'material-request-component' :
                $format = "MRM".$project_site_id.date_format($created_at,'y').date_format($created_at,'m').date_format($created_at,'d').$serial_no;
            break;

            case 'purchase-request' :
                $format = "PR".$project_site_id.date_format($created_at,'y').date_format($created_at,'m').date_format($created_at,'d').$serial_no;
            break;

            case 'purchase-order' :
                $format = "PO".$project_site_id.date_format($created_at,'y').date_format($created_at,'m').date_format($created_at,'d').$serial_no;
            break;

            default :
                 $format = "";
            break;
        }
        return $format;
    }
}
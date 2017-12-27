<?php
/**
 * Created by Ameya Joshi.
 * Date: 27/12/17
 * Time: 2:27 PM
 */

namespace App\Http\Controllers\CustomTraits;

use Illuminate\Support\Facades\Log;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use FCM;

trait NotificationTrait{
    public function sendPushNotification($title,$body,$tokens){
        try{
            $optionBuilder = new OptionsBuilder();
            $optionBuilder->setTimeToLive(60*20);
            $notificationBuilder = new PayloadNotificationBuilder('Material Request Created');
            $notificationBuilder->setBody('ABC has created a material request for site XYZ')
                        ->setSound('default');
            $dataBuilder = new PayloadDataBuilder();
            /*$dataBuilder->addData(['a_data' => 'my_data']);*/
            $option = $optionBuilder->build();
            $notification = $notificationBuilder->build();
            $data = $dataBuilder->build();
            $downstreamResponse = FCM::sendTo($tokens, $option, $notification, $data);
            return true;
        }catch(\Exception $e){
            $data = [
                'action' => 'Send Push Notification',
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
            return null;
        }
    }
}
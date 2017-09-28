<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
    return $app->version();
});

$app->post('/login',array('uses' => 'AuthController@login'));
$app->post('/dashboard',array('uses' => 'AuthController@dashboard'));
$app->post('save-image',array('uses' => 'ImageController@saveImages'));
$app->group(['prefix' => 'inventory'],function () use($app){
    $app->group(['prefix' => 'material'],function () use($app){
        $app->post('listing', array('uses' => 'Inventory\InventoryManageController@getMaterialListing'));
    });
    $app->group(['prefix' => 'asset'],function () use($app){
        $app->post('listing', array('uses' => 'Inventory\AssetManagementController@getAssetListing'));
        $app->post('summary-listing', array('uses' => 'Inventory\AssetManagementController@getSummaryAssetListing'));
        $app->post('request-maintenance', array('uses' => 'Inventory\AssetManagementController@createRequestMaintenance'));
    });
});
$app->group(['prefix' => 'purchase'],function () use($app){
    $app->get('get-purchase-request-status',array('uses' => 'Purchase\MaterialRequestController@getPurchaseRequestComponentStatus'));
    $app->group(['prefix' => 'material-request'],function () use ($app){
        $app->post('create',array('uses' => 'Purchase\MaterialRequestController@createMaterialRequestData'));
        $app->post('change-status',array('uses' => 'Purchase\MaterialRequestController@changeStatus'));
        $app->post('listing',array('uses' => 'Purchase\MaterialRequestController@materialRequestListing'));

    });
    $app->group(['prefix' => 'purchase-request'],function () use ($app){
        $app->post('create',array('uses' => 'Purchase\PurchaseRequestController@createPurchaseRequest'));
        $app->post('change-status',array('uses' => 'Purchase\PurchaseRequestController@changeStatus'));
        $app->post('listing',array('uses' => 'Purchase\PurchaseRequestController@purchaseRequestListing'));
        $app->post('detail-listing',array('uses' => 'Purchase\PurchaseRequestController@getDetailListing'));
    });

});
$app->post('auto-suggest',array('uses' => 'Purchase\MaterialRequestController@autoSuggest'));
$app->group(['prefix' => 'users'], function () use($app){
    $app->group(['prefix' => 'purchase'], function () use($app){
            $app->post('purchase-request/approval-acl', array('uses' => 'User\PurchaseController@getPurchaseRequestApprovalACl'));
    });
});


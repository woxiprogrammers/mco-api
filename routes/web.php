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
    $app->post('create-transfer', array('uses' => 'Inventory\InventoryManageController@createInventoryTransfer'));
    $app->group(['prefix' => 'material'],function () use($app){
        $app->post('listing', array('uses' => 'Inventory\InventoryManageController@getMaterialListing'));
    });
    $app->group(['prefix' => 'asset'],function () use($app){
        $app->post('listing', array('uses' => 'Inventory\AssetManagementController@getAssetListing'));
        $app->post('summary-listing', array('uses' => 'Inventory\AssetManagementController@getSummaryAssetListing'));
        $app->post('request-maintenance', array('uses' => 'Inventory\AssetManagementController@createRequestMaintenance'));
        $app->post('add-readings',array('uses' => 'Inventory\AssetManagementController@addReadings'));
    });
});
$app->group(['prefix' => 'purchase'],function () use($app){
    $app->get('get-purchase-request-status',array('uses' => 'Purchase\MaterialRequestController@getPurchaseRequestComponentStatus'));
    $app->group(['prefix' => 'material-request'],function () use ($app){
        $app->post('create',array('uses' => 'Purchase\MaterialRequestController@createMaterialRequestData'));
        $app->post('change-status',array('uses' => 'Purchase\MaterialRequestController@changeStatus'));
        $app->post('listing',array('uses' => 'Purchase\MaterialRequestController@materialRequestListing'));
        $app->post('check-available-quantity',array('uses' => 'Purchase\MaterialRequestController@checkAvailableQuantity'));

    });
    $app->group(['prefix' => 'purchase-request'],function () use ($app){
        $app->post('create',array('uses' => 'Purchase\PurchaseRequestController@createPurchaseRequest'));
        $app->post('change-status',array('uses' => 'Purchase\PurchaseRequestController@changeStatus'));
        $app->post('listing',array('uses' => 'Purchase\PurchaseRequestController@purchaseRequestListing'));
        $app->post('detail-listing',array('uses' => 'Purchase\PurchaseRequestController@getDetailListing'));
    });
    $app->group(['prefix' => 'purchase-order'], function () use ($app){
        $app->post('listing',array('uses' => 'Purchase\PurchaseOrderController@getPurchaseOrderListing'));
        $app->post('material-listing',array('uses' => 'Purchase\PurchaseOrderController@getPurchaseOrderMaterialListing'));
        $app->post('bill-transaction',array('uses' => 'Purchase\PurchaseOrderController@createPurchaseOrderBillTransaction'));
        $app->post('edit-bill-transaction',array('uses' => 'Purchase\PurchaseOrderController@editPurchaseOrderBillTransaction'));
        $app->post('bill-listing',array('uses' => 'Purchase\PurchaseOrderController@getPurchaseOrderBillTransactionListing'));
        $app->post('bill-payment',array('uses' => 'Purchase\PurchaseOrderController@createBillPayment'));
    });

});

$app->post('auto-suggest',array('uses' => 'Purchase\MaterialRequestController@autoSuggest'));
$app->group(['prefix' => 'users'], function () use($app){
    $app->group(['prefix' => 'purchase'], function () use($app){
            $app->post('purchase-request/approval-acl', array('uses' => 'User\PurchaseController@getPurchaseRequestApprovalACl'));
    });
});
$app->group(['prefix' => 'peticash'], function () use($app){
    $app->post('transaction/listing', array('uses' => 'Peticash\SalaryController@getTransactionListing'));
    $app->group(['prefix' => 'employee-salary'], function () use($app){
        $app->post('auto-suggest', array('uses' => 'Peticash\SalaryController@autoSuggest'));
        $app->post('create', array('uses' => 'Peticash\SalaryController@createSalary'));
        $app->post('employee-detail', array('uses' => 'Peticash\SalaryController@getEmployeeDetails'));
        $app->post('transaction-detail', array('uses' => 'Peticash\SalaryController@getTransactionDetails'));
    });
    $app->group(['prefix' => 'purchase'], function () use($app){
        $app->post('create', array('uses' => 'Peticash\PurchaseController@createPurchase'));
        $app->post('transaction-detail', array('uses' => 'Peticash\PurchaseController@getTransactionDetails'));
    });
});
$app->get('system-units' , array('uses' => 'UnitController@getAllSystemUnits'));

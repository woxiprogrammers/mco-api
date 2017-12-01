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
        $app->post('check-availability', array('uses' => 'Inventory\InventoryManageController@checkAvailableQuantity'));
    });
    $app->group(['prefix' => 'asset'],function () use($app){
        $app->post('listing', array('uses' => 'Inventory\AssetManagementController@getAssetListing'));
        $app->post('summary-listing', array('uses' => 'Inventory\AssetManagementController@getSummaryAssetListing'));
        $app->post('request-maintenance', array('uses' => 'Inventory\AssetManagementController@createRequestMaintenance'));
        $app->group(['prefix' => 'readings'],function() use($app){
            $app->post('add',array('uses' => 'Inventory\AssetManagementController@addReadings'));
            $app->post('listing',array('uses' => 'Inventory\AssetManagementController@readingListing'));
        });
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
        $app->post('detail',array('uses' => 'Purchase\PurchaseOrderController@getPurchaseOrderDetail'));
        $app->post('material-listing',array('uses' => 'Purchase\PurchaseOrderController@getPurchaseOrderMaterialListing'));
        $app->post('generate-grn',array('uses' => 'Purchase\PurchaseOrderController@generateGRN'));
        $app->post('create-transaction',array('uses' => 'Purchase\PurchaseOrderController@createPurchaseOrderTransaction'));
        /*$app->post('edit-bill-transaction',array('uses' => 'Purchase\PurchaseOrderController@editPurchaseOrderBillTransaction'));*/
        $app->post('transaction-listing',array('uses' => 'Purchase\PurchaseOrderController@getPurchaseOrderTransactionListing'));
        /*$app->post('bill-payment',array('uses' => 'Purchase\PurchaseOrderController@createBillPayment'));*/
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
    $app->post('statistics', array('uses' => 'Peticash\SalaryController@getStatistics'));
    $app->group(['prefix' => 'employee-salary'], function () use($app){
        $app->post('auto-suggest', array('uses' => 'Peticash\SalaryController@autoSuggest'));
        $app->post('create', array('uses' => 'Peticash\SalaryController@createSalary'));
        $app->post('employee-detail', array('uses' => 'Peticash\SalaryController@getEmployeeDetails'));
        $app->post('transaction-detail', array('uses' => 'Peticash\SalaryController@getTransactionDetails'));
    });
    $app->group(['prefix' => 'purchase'], function () use($app){
        $app->post('create', array('uses' => 'Peticash\PurchaseController@createPurchase'));
        $app->post('bill-payment', array('uses' => 'Peticash\PurchaseController@createBillPayment'));
        $app->post('transaction-detail', array('uses' => 'Peticash\PurchaseController@getTransactionDetails'));
    });
});
$app->group(['prefix' => 'checklist'], function () use($app){
    $app->post('category', array('uses' => 'Checklist\ChecklistController@getCategoryListing'));
    $app->post('floor', array('uses' => 'Checklist\ChecklistController@getFloorListing'));
    $app->post('title', array('uses' => 'Checklist\ChecklistController@getTitleListing'));
    $app->post('assign', array('uses' => 'Checklist\ChecklistController@createUserAssignment'));
    $app->post('listing', array('uses' => 'Checklist\ChecklistController@getChecklistListing'));
    $app->post('checkpoint-listing', array('uses' => 'Checklist\ChecklistController@getCheckPointListing'));
    $app->post('description', array('uses' => 'Checklist\ChecklistController@getDescriptionListing'));
    $app->post('get-user-with-assign-acl', array('uses' => 'Checklist\ChecklistController@getUserWithAssignAcl'));
    $app->post('save-checklist-detail',array('uses' => 'Checklist\ChecklistController@saveChecklistDetails'));
    $app->post('change-status',array('uses' => 'Checklist\ChecklistController@changeChecklistStatus'));
});

$app->get('system-units' , array('uses' => 'UnitController@getAllSystemUnits'));
$app->get('system-project-sites' , array('uses' => 'ProjectSiteController@getAllProjectSites'));
$app->group(['prefix' => 'awareness'], function () use($app){
    $app->post('get-main-categories', array('uses' => 'Awareness\AwarenessManagementController@getMainCategories'));
    $app->post('get-sub-categories', array('uses' => 'Awareness\AwarenessManagementController@getSubCategories'));
    $app->post('listing', array('uses' => 'Awareness\AwarenessManagementController@listing'));
});
    $app->group(['prefix' => 'drawing'], function () use($app){
        $app->post('get-main-categories', array('uses' => 'Drawing\DrawingController@getMainCategories'));
        $app->post('get-sub-categories', array('uses' => 'Drawing\DrawingController@getSubCategories'));
        $app->post('get-current-version-images', array('uses' => 'Drawing\DrawingController@getCurrentVersionImages'));
        $app->post('add-comment', array('uses' => 'Drawing\DrawingController@addComment'));
        $app->post('get-comments', array('uses' => 'Drawing\DrawingController@getComments'));
        $app->post('get-all-image-versions', array('uses' => 'Drawing\DrawingController@getAllImageVersions'));
    });

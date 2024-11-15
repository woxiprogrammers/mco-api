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
$app->get('/app-version', array('uses' => 'AuthController@getAppVersion'));
$app->post('/login', array('uses' => 'AuthController@login'));
$app->post('/logout', array('uses' => 'AuthController@logout'));
$app->post('/dashboard', array('uses' => 'AuthController@dashboard'));
$app->post('save-image', array('uses' => 'ImageController@saveImages'));

$app->group(['prefix' => 'inventory'], function () use ($app) {
    $app->group(['prefix' => 'challan'], function () use ($app) {
        $app->get('pending/project-site-id/{projectSiteId}', array('uses' => 'Inventory\InventoryTransferChallanController@getPendingChallans'));
        $app->post('{challanId}/project-site-id/{projectSiteId}/site-in', array('uses' => 'Inventory\InventoryTransferChallanController@generateSiteIn'));
        $app->patch('{challanId}/project-site-id/{projectSiteId}/site-in', array('uses' => 'Inventory\InventoryTransferChallanController@createSiteIn'));
        $app->get('{challanId}/project-site-id/{projectSiteId}', array('uses' => 'Inventory\InventoryTransferChallanController@getChallanDetail'));
    });
    $app->post('generate-grn', array('uses' => 'Inventory\InventoryManageController@generateGRN'));
    $app->post('get-site-out-GRN', array('uses' => 'Inventory\InventoryManageController@getSiteOutGRN'));
    $app->post('post-grn-transfer', array('uses' => 'Inventory\InventoryManageController@postGrnInventoryTransfer'));
    $app->post('create-transfer', array('uses' => 'Inventory\InventoryManageController@createInventoryTransfer'));
    $app->post('request-component-listing', array('uses' => 'Inventory\InventoryManageController@getSiteTransferRequestListing'));
    $app->post('component/auto-suggest', array('uses' => 'Inventory\InventoryManageController@autoSuggest'));
    $app->post('component/get-grn-details', array('uses' => 'Inventory\InventoryManageController@getGRNDetails'));
    $app->post('change-status', array('uses' => 'Inventory\InventoryManageController@changeStatus'));
    $app->group(['prefix' => 'material'], function () use ($app) {
        $app->post('listing', array('uses' => 'Inventory\InventoryManageController@getMaterialListing'));
        $app->post('check-availability', array('uses' => 'Inventory\InventoryManageController@checkAvailableQuantity'));
    });
    $app->group(['prefix' => 'asset'], function () use ($app) {
        $app->post('listing', array('uses' => 'Inventory\AssetManagementController@getAssetListing'));
        $app->post('summary-listing', array('uses' => 'Inventory\AssetManagementController@getSummaryAssetListing'));
        //$app->post('request-maintenance', array('uses' => 'Inventory\AssetManagementController@createRequestMaintenance'));
        $app->group(['prefix' => 'readings'], function () use ($app) {
            $app->post('add', array('uses' => 'Inventory\AssetManagementController@addReadings'));
            $app->post('listing', array('uses' => 'Inventory\AssetManagementController@readingListing'));
        });
        $app->group(['prefix' => 'maintenance-request'], function () use ($app) {
            $app->post('create', array('uses' => 'Inventory\AssetMaintenanceController@createAssetMaintenanceRequest'));
            $app->post('listing', array('uses' => 'Inventory\AssetMaintenanceController@getAssetRequestMaintenanceListing'));
            $app->group(['prefix' => 'transaction'], function () use ($app) {
                $app->post('generate-grn', array('uses' => 'Inventory\AssetMaintenanceController@generateAssetMaintenanceRequestGRN'));
                $app->post('create', array('uses' => 'Inventory\AssetMaintenanceController@createTransaction'));
            });
        });
    });
});

$app->group(['prefix' => 'purchase'], function () use ($app) {
    $app->get('get-purchase-request-status', array('uses' => 'Purchase\MaterialRequestController@getPurchaseRequestComponentStatus'));
    $app->post('get-history', array('uses' => 'Purchase\MaterialRequestController@getPurchaseRequestHistory'));
    $app->group(['prefix' => 'material-request'], function () use ($app) {
        $app->post('create', array('uses' => 'Purchase\MaterialRequestController@createMaterialRequestData'));
        $app->post('change-status', array('uses' => 'Purchase\MaterialRequestController@changeStatus'));
        $app->post('listing', array('uses' => 'Purchase\MaterialRequestController@materialRequestListing'));
        $app->post('check-available-quantity', array('uses' => 'Purchase\MaterialRequestController@checkAvailableQuantity'));
    });
    $app->group(['prefix' => 'purchase-request'], function () use ($app) {
        $app->post('create', array('uses' => 'Purchase\PurchaseRequestController@createPurchaseRequest'));
        $app->post('change-status', array('uses' => 'Purchase\PurchaseRequestController@changeStatus'));
        $app->post('listing', array('uses' => 'Purchase\PurchaseRequestController@purchaseRequestListing'));
        $app->post('detail-listing', array('uses' => 'Purchase\PurchaseRequestController@getDetailListing'));
    });
    $app->group(['prefix' => 'purchase-order-request'], function () use ($app) {
        $app->post('listing', array('uses' => 'Purchase\PurchaseOrderRequestController@getPurchaseOrderRequestListing'));
        $app->post('detail', array('uses' => 'Purchase\PurchaseOrderRequestController@getPurchaseOrderRequestDetail'));
        $app->post('change-status', array('uses' => 'Purchase\PurchaseOrderRequestController@changeStatus'));
        $app->post('disapprove-component', array('uses' => 'Purchase\PurchaseOrderRequestController@disapproveComponent'));
    });
    $app->group(['prefix' => 'purchase-order'], function () use ($app) {
        $app->post('listing', array('uses' => 'Purchase\PurchaseOrderController@getPurchaseOrderListing'));
        $app->post('detail', array('uses' => 'Purchase\PurchaseOrderController@getPurchaseOrderDetail'));
        $app->post('material-listing', array('uses' => 'Purchase\PurchaseOrderController@getPurchaseOrderMaterialListing'));
        $app->post('change-status', array('uses' => 'Purchase\PurchaseOrderController@changeStatus'));
        $app->post('authenticate-purchase-order-close', array('uses' => 'Purchase\PurchaseOrderController@authenticatePOClose'));
        $app->post('generate-grn', array('uses' => 'Purchase\PurchaseOrderController@generateGRN'));
        $app->post('create-transaction', array('uses' => 'Purchase\PurchaseOrderController@createPurchaseOrderTransaction'));
        /*$app->post('edit-bill-transaction',array('uses' => 'Purchase\PurchaseOrderController@editPurchaseOrderBillTransaction'));*/
        $app->post('transaction-listing', array('uses' => 'Purchase\PurchaseOrderController@getPurchaseOrderTransactionListing'));
        /*$app->post('bill-payment',array('uses' => 'Purchase\PurchaseOrderController@createBillPayment'));*/
    });
});

$app->post('auto-suggest', array('uses' => 'Purchase\MaterialRequestController@autoSuggest'));

$app->group(['prefix' => 'users'], function () use ($app) {
    $app->group(['prefix' => 'purchase'], function () use ($app) {
        $app->post('purchase-request/approval-acl', array('uses' => 'User\PurchaseController@getPurchaseRequestApprovalACl'));
    });
});

$app->group(['prefix' => 'peticash'], function () use ($app) {
    $app->post('transaction/listing', array('uses' => 'Peticash\SalaryController@getTransactionListing'));
    $app->post('statistics', array('uses' => 'Peticash\SalaryController@getStatistics'));
    $app->group(['prefix' => 'employee-salary'], function () use ($app) {
        $app->post('auto-suggest', array('uses' => 'Peticash\SalaryController@autoSuggest'));
        $app->post('create', array('uses' => 'Peticash\SalaryController@createSalary'));
        $app->post('calulate-payable-amount', array('uses' => 'Peticash\SalaryController@calculatePayableAmount'));
        $app->post('employee-detail', array('uses' => 'Peticash\SalaryController@getEmployeeDetails'));
        $app->post('transaction-detail', array('uses' => 'Peticash\SalaryController@getTransactionDetails'));
        $app->post('generate-payment-voucher', array('uses' => 'Peticash\SalaryController@getPaymentVoucherPdf'));
        $app->post('delete-payment-voucher', array('uses' => 'Peticash\SalaryController@deletePaymentVoucherPdf'));
    });
    $app->group(['prefix' => 'purchase'], function () use ($app) {
        $app->post('create', array('uses' => 'Peticash\PurchaseController@createPurchase'));
        $app->post('bill-payment', array('uses' => 'Peticash\PurchaseController@createBillPayment'));
        $app->post('transaction-detail', array('uses' => 'Peticash\PurchaseController@getTransactionDetails'));
    });
});

$app->group(['prefix' => 'checklist'], function () use ($app) {
    $app->post('category', array('uses' => 'Checklist\ChecklistController@getCategoryListing'));
    $app->post('floor', array('uses' => 'Checklist\ChecklistController@getFloorListing'));
    $app->post('title', array('uses' => 'Checklist\ChecklistController@getTitleListing'));
    $app->post('assign', array('uses' => 'Checklist\ChecklistController@createUserAssignment'));
    $app->post('listing', array('uses' => 'Checklist\ChecklistController@getChecklistListing'));
    $app->post('checkpoint-listing', array('uses' => 'Checklist\ChecklistController@getCheckPointListing'));
    $app->post('description', array('uses' => 'Checklist\ChecklistController@getDescriptionListing'));
    $app->post('get-user-with-assign-acl', array('uses' => 'Checklist\ChecklistController@getUserWithAssignAcl'));
    $app->post('save-checkpoint-detail', array('uses' => 'Checklist\ChecklistController@saveCheckpointDetails'));
    $app->post('change-status', array('uses' => 'Checklist\ChecklistController@changeChecklistStatus'));
    $app->post('recheck-checkpoint', array('uses' => 'Checklist\ChecklistController@recheckCheckpoints'));
    $app->post('get-parent', array('uses' => 'Checklist\ChecklistController@getParentChecklist'));
});

$app->get('system-units', array('uses' => 'UnitController@getAllSystemUnits'));

$app->get('system-project-sites', array('uses' => 'ProjectSiteController@getAllProjectSites'));

$app->get('system-miscellaneous-categories', array('uses' => 'MiscellaneousCategoryController@getAllMiscellaneousCategory'));

$app->group(['prefix' => 'awareness'], function () use ($app) {
    $app->post('get-main-categories', array('uses' => 'Awareness\AwarenessManagementController@getMainCategories'));
    $app->post('get-sub-categories', array('uses' => 'Awareness\AwarenessManagementController@getSubCategories'));
    $app->post('listing', array('uses' => 'Awareness\AwarenessManagementController@listing'));
});

$app->group(['prefix' => 'drawing'], function () use ($app) {
    $app->post('get-main-categories', array('uses' => 'Drawing\DrawingController@getMainCategories'));
    $app->post('get-sub-categories', array('uses' => 'Drawing\DrawingController@getSubCategories'));
    $app->post('get-current-version-images', array('uses' => 'Drawing\DrawingController@getCurrentVersionImages'));
    $app->post('add-comment', array('uses' => 'Drawing\DrawingController@addComment'));
    $app->post('get-comments', array('uses' => 'Drawing\DrawingController@getComments'));
    $app->post('get-all-image-versions', array('uses' => 'Drawing\DrawingController@getAllImageVersions'));
});

$app->group(['prefix' => 'notification'], function () use ($app) {
    $app->post('store-fcm-token', array('uses' => 'Notification\NotificationController@storeFCMToken'));
    $app->post('get-counts', array('uses' => 'Notification\NotificationController@getNotificationCounts'));
    $app->get('send-push-notification', array('uses' => 'Notification\NotificationController@sendPushNotification'));
    $app->post('project-site/get-count', array('uses' => 'Notification\NotificationController@getProjectSiteWiseCount'));
});

$app->group(['prefix' => 'dpr'], function () use ($app) {
    $app->group(['prefix' => 'subcontractor'], function () use ($app) {
        $app->post('listing', array('uses' => 'DPR\DprController@subcontractorListing'));
        $app->post('save-details', array('uses' => 'DPR\DprController@saveDetails'));
        $app->post('dpr-detail-listing', array('uses' => 'DPR\DprController@dprDetailsListing'));
    });
    $app->group(['prefix' => 'category'], function () use ($app) {
        $app->post('listing', array('uses' => 'DPR\DprController@categoryListing'));
    });
});

$app->get('system-banks', array('uses' => 'BankController@getAllBanks'));

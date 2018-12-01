<?php

namespace App\Http\Controllers\Peticash;

use App\AssetMaintenanceBillPayment;
use App\BankInfo;
use App\BillReconcileTransaction;
use App\BillTransaction;
use App\Employee;
use App\EmployeeImage;
use App\EmployeeImageType;
use App\EmployeeType;
use App\Helper\NumberHelper;
use App\PaymentType;
use App\PeticashRequestedSalaryTransaction;
use App\PeticashSalaryTransaction;
use App\PeticashSalaryTransactionImages;
use App\PeticashSiteApprovedAmount;
use App\PeticashSiteTransfer;
use App\PeticashStatus;
use App\PeticashTransactionType;
use App\Project;
use App\ProjectSite;
use App\ProjectSiteAdvancePayment;
use App\ProjectSiteIndirectExpense;
use App\PurchaseOrderAdvancePayment;
use App\PurchaseOrderPayment;
use App\PurchasePeticashTransaction;
use App\SiteTransferBillPayment;
use App\SubcontractorAdvancePayment;
use App\SubcontractorBillReconcileTransaction;
use App\SubcontractorBillTransaction;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Mockery\Exception;

class SalaryController extends BaseController{
    public function __construct(){
        $this->middleware('jwt.auth',['except' => ['autoSuggest']]);
        if(!Auth::guest()) {
            $this->user = Auth::user();
        }
    }

    public function autoSuggest(Request $request){
        try{
            $status = 200;
            $message = "Success";
            $iterator = 0;
            $employeeDetails = Employee::where('employee_id','ilike','%'.$request->employee_name.'%')->orWhere('name','ilike','%'.$request->employee_name.'%')->whereIn('employee_type_id',EmployeeType::whereIn('slug',['labour','staff','partner'])->pluck('id'))->where('is_active',true)->get()->toArray();
            $data = array();
            foreach($employeeDetails as $key => $employeeDetail){
                $data[$iterator]['employee_id'] = $employeeDetail['id'];
                $data[$iterator]['format_employee_id'] = $employeeDetail['employee_id'];
                $data[$iterator]['employee_name'] = $employeeDetail['name'];
                $data[$iterator]['per_day_wages'] = $employeeDetail['per_day_wages'];
                $data[$iterator]['employee_profile_picture'] = '/assets/global/img/logo.jpg';
                $profilePicTypeId = EmployeeImageType::where('slug','profile')->pluck('id')->first();
                $employeeProfilePic = EmployeeImage::where('employee_id',$employeeDetail['id'])->where('employee_image_type_id',$profilePicTypeId)->first();
                if($employeeProfilePic == null){
                    $data[$iterator]['employee_profile_picture'] = "";
                }else{
                    $employeeDirectoryName = sha1($employeeDetail['id']);
                    $imageUploadPath = env('EMPLOYEE_IMAGE_UPLOAD').DIRECTORY_SEPARATOR.$employeeDirectoryName.DIRECTORY_SEPARATOR.'profile';
                    $data[$iterator]['employee_profile_picture'] = $imageUploadPath.DIRECTORY_SEPARATOR.$employeeProfilePic->name;
                }
                $peticashStatus = PeticashStatus::whereIn('slug',['approved','pending'])->select('id','slug')->get();
                $transactionPendingCount = PeticashSalaryTransaction::where('project_site_id',$request['project_site_id'])->where('employee_id',$employeeDetail['id'])->where('peticash_status_id',$peticashStatus->where('slug','pending')->pluck('id')->first())->count();
                $data[$iterator]['is_transaction_pending'] = ($transactionPendingCount > 0) ? true : false;
                $salaryTransactions = PeticashSalaryTransaction::where('project_site_id',$request['project_site_id'])->where('employee_id',$employeeDetail['id'])->where('peticash_status_id',$peticashStatus->where('slug','approved')->pluck('id')->first())->select('id','amount','payable_amount','peticash_transaction_type_id','pf','pt','esic','tds','created_at')->get();
                $paymentSlug = PeticashTransactionType::where('type','PAYMENT')->select('id','slug')->get();
                $advanceSalaryTotal = round($salaryTransactions->where('peticash_transaction_type_id',$paymentSlug->where('slug','advance')->pluck('id')->first())->sum('amount'),1);
                $actualSalaryTotal = round($salaryTransactions->where('peticash_transaction_type_id',$paymentSlug->where('slug','salary')->pluck('id')->first())->sum('amount'),1);
                $payableSalaryTotal = round($salaryTransactions->sum('payable_amount'),1);
                $pfTotal = round($salaryTransactions->sum('pf'),1);
                $ptTotal = round($salaryTransactions->sum('pt'),1);
                $esicTotal = round($salaryTransactions->sum('esic'),1);
                $tdsTotal = round($salaryTransactions->sum('tds'),1);
                $data[$iterator]['balance'] = round((round((round(($actualSalaryTotal - $advanceSalaryTotal),1) - $payableSalaryTotal),1) - $pfTotal - $ptTotal - $esicTotal - $tdsTotal) ,1);
                $lastSalaryId = $salaryTransactions->where('peticash_transaction_type_id',$paymentSlug->where('slug','salary')->pluck('id')->first())->sortByDesc('created_at')->pluck('id')->first();
                $advanceAfterLastSalary = $salaryTransactions->where('peticash_transaction_type_id',$paymentSlug->where('slug','advance')->pluck('id')->first())->where('id','>',$lastSalaryId)->sum('amount');
                $data[$iterator]['advance_after_last_salary'] = $advanceAfterLastSalary;
                $iterator++;
            }
        }catch(Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Auto Suggestion for Employee',
                'params' => $request->all(),
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message,
            "data" => $data,
        ];
        return response()->json($response,$status);
    }

    public function createSalary(Request $request){
        try{
            $user = Auth::user();
            $salaryData = $request->except('token','images','type');
            $salaryData['reference_user_id'] = $user['id'];
            $salaryData['peticash_transaction_type_id'] = PeticashTransactionType::where('slug','ilike',$request['type'])->pluck('id')->first();
            $salaryData['peticash_status_id'] = PeticashStatus::where('slug','approved')->pluck('id')->first();
            $salaryData['created_at'] = $salaryData['updated_at'] = Carbon::now();
            if($request['paid_from'] == 'bank'){
                $bank = BankInfo::where('id',$request['bank_id'])->first();
                $salaryData['payment_type_id'] = PaymentType::where('slug',$request['payment_mode_slug'])->pluck('id')->first();
                $salaryData['bank_id'] = $request['bank_id'];
                $salaryTransaction = PeticashSalaryTransaction::create($salaryData);
                $bankData['balance_amount'] = $bank['balance_amount'] - $request['amount'];
                $bank->update($bankData);
            }else{
                $salaryData['payment_type_id'] = PaymentType::where('slug','peticash')->pluck('id')->first();
                $salaryTransaction = PeticashSalaryTransaction::create($salaryData);
            }
            $salaryTransactionId = $salaryTransaction['id'];
            $peticashSiteApprovedAmount = PeticashSiteApprovedAmount::where('project_site_id',$request['project_site_id'])->first();
            $updatedPeticashSiteApprovedAmount = $peticashSiteApprovedAmount['salary_amount_approved'] - $request['amount'];
            $peticashSiteApprovedAmount->update(['salary_amount_approved' => $updatedPeticashSiteApprovedAmount]);
            $officeSiteId = ProjectSite::where('name',env('OFFICE_PROJECT_SITE_NAME'))->pluck('id')->first();
            if($request['project_site_id'] == $officeSiteId){
                $activeProjectSites = ProjectSite::join('projects','projects.id','=','project_sites.project_id')
                    ->where('projects.is_active',true)
                    ->where('project_sites.id','!=',$officeSiteId)->get();
                if($request['type'] == 'advance'){
                    $distributedSiteWiseAmount =  $salaryTransaction['amount'] / count($activeProjectSites);
                }else{
                    $distributedSiteWiseAmount = ($salaryTransaction['payable_amount'] + $salaryTransaction['pf'] + $salaryTransaction['pt'] + $salaryTransaction['tds'] + $salaryTransaction['esic']) / count($activeProjectSites) ;
                }
                foreach ($activeProjectSites as $key => $projectSite){
                    $distributedSalaryAmount = $projectSite['distributed_salary_amount'] + $distributedSiteWiseAmount;
                    $projectSite->update([
                        'distributed_salary_amount' => $distributedSalaryAmount
                    ]);
                }
            }

            if(array_has($request,'images')){
                $user = Auth::user();
                $sha1UserId = sha1($user['id']);
                $sha1SalaryTransactionId = sha1($salaryTransactionId);
                foreach($request['images'] as $key1 => $imageName){
                    $tempUploadFile = env('WEB_PUBLIC_PATH').env('PETICASH_SALARY_TRANSACTION_TEMP_IMAGE_UPLOAD').$sha1UserId.DIRECTORY_SEPARATOR.$imageName;
                    if(File::exists($tempUploadFile)){
                        $imageUploadNewPath = env('WEB_PUBLIC_PATH').env('PETICASH_SALARY_TRANSACTION_IMAGE_UPLOAD').$sha1SalaryTransactionId;
                        if(!file_exists($imageUploadNewPath)) {
                            File::makeDirectory($imageUploadNewPath, $mode = 0777, true, true);
                        }
                        $imageUploadNewPath .= DIRECTORY_SEPARATOR.$imageName;
                        File::move($tempUploadFile,$imageUploadNewPath);
                        PeticashSalaryTransactionImages::create(['name' => $imageName,'peticash_salary_transaction_id' => $salaryTransactionId]);
                    }
                }
            }
            $status = 200;
            $message = "Salary transaction created successfully";
        }catch(\Exception $e){
            $status = 500;
            $message = "Fail";
            $data = [
                'action' => 'Create Salary',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
        ];
        return response()->json($response,$status);
    }

    public function getEmployeeDetails(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $data = array();
            $employeeData = Employee::where('id',$request['employee_id'])->first();
            $data['employee_id'] = $employeeData['id'];
            $data['employee_name'] = $employeeData['name'];
            $data['format_employee_id'] = $employeeData['employee_id'];
            $employeeTransactionDetails = PeticashSalaryTransaction::where('employee_id',$request['employee_id'])->orderBy('created_at','desc')->get();
            $paymentSlug = PeticashTransactionType::where('type','PAYMENT')->select('id','slug')->get();
            $advancePaymentTypeId = $paymentSlug->where('slug','advance')->pluck('id')->first();
            $iterator = 0;
            $transactions = array();
            foreach ($employeeTransactionDetails as $key => $transactionDetail){
                $transactions[$iterator]['peticash_salary_transaction_id'] = $transactionDetail['id'];
                if($transactionDetail['peticash_transaction_type_id'] == $advancePaymentTypeId){
                    $transactions[$iterator]['advance_amount'] = (int)$transactionDetail['amount'];
                    $transactions[$iterator]['salary_amount'] = 0;
                    $transactions[$iterator]['payable_amount'] = 0;
                }else{
                    $transactions[$iterator]['advance_amount'] = 0;
                    $transactions[$iterator]['salary_amount'] = (int)$transactionDetail['amount'];
                    $transactions[$iterator]['payable_amount'] = (int)$transactionDetail['payable_amount'];
                }
                $transactions[$iterator]['date'] = $transactionDetail['date'];
                $transactions[$iterator]['type'] = $transactionDetail->peticashTransactionType->name;
                $transactions[$iterator]['transaction_status_id'] = $transactionDetail['peticash_status_id'];
                $transactions[$iterator]['transaction_status_name'] = $transactionDetail->peticashStatus->name;
                $transactions[$iterator]['project_site_id'] = $transactionDetail['project_site_id'];
                $transactions[$iterator]['project_site_name'] = $transactionDetail->projectSite->name;
                $iterator++;
            }
            $data['employee_transactions'] = $transactions;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Get Employee Details',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            "message" => $message,
            "data" => $data
        ];
        return response()->json($response,$status);
    }

    public function getTransactionListing(Request $request){
        try{
            $user = Auth::user();
            $amountLimit = User::where('id',$user['id'])->pluck('purchase_peticash_amount_limit')->first();
            $data['peticash_purchase_amount_limit'] = ($amountLimit != null) ? $amountLimit : '0';
            $listingData = array();
            switch ($request['type']){
                case 'both' :
                    $purchaseTransactionData = PurchasePeticashTransaction::
                                                where('project_site_id',$request['project_site_id'])
                                                ->whereMonth('date', $request['month'])
                                                ->whereYear('date', $request['year'])
                                                ->orderBy('date','desc')
                                                ->select('id as purchase_id','name','project_site_id','component_type_id','payment_type_id','peticash_transaction_type_id','bill_amount','date','peticash_status_id','created_at')
                                                ->get();
                    $salaryTransactionData = PeticashSalaryTransaction::where('project_site_id',$request['project_site_id'])
                                            ->whereMonth('date', $request['month'])->whereYear('date', $request['year'])
                                            ->orderBy('date','desc')
                                            ->select('id as salary_id','employee_id','project_site_id','peticash_transaction_type_id','amount','date','peticash_status_id','payment_type_id','created_at','payable_amount')
                                            ->get();

                    $projectSiteId = $request['project_site_id'];
                    $purchaseOrderAdvancePayments = PurchaseOrderAdvancePayment::join('purchase_orders','purchase_orders.id','purchase_order_advance_payments.purchase_order_id')
                        ->join('vendors','vendors.id','=','purchase_orders.vendor_id')
                        ->join('purchase_requests','purchase_requests.id','=','purchase_orders.purchase_request_id')
                        ->where('purchase_order_advance_payments.paid_from_slug','cash')
                        ->where('purchase_requests.project_site_id',$projectSiteId)
                        ->whereMonth('purchase_order_advance_payments.created_at', $request['month'])->whereYear('purchase_order_advance_payments.created_at', $request['year'])->orderBy('purchase_order_advance_payments.created_at','desc')
                        ->select('purchase_order_advance_payments.id as payment_id','purchase_order_advance_payments.amount as amount'
                            ,'purchase_order_advance_payments.created_at as date','purchase_requests.project_site_id as project_site_id'
                            ,'vendors.company as name')->get();

                    $purchaseOrderBillPayments = PurchaseOrderPayment::join('purchase_order_bills','purchase_order_bills.id','=','purchase_order_payments.purchase_order_bill_id')
                        ->join('purchase_orders','purchase_orders.id','=','purchase_order_bills.purchase_order_id')
                        ->join('vendors','vendors.id','=','purchase_orders.vendor_id')
                        ->join('purchase_requests','purchase_requests.id','=','purchase_orders.purchase_request_id')
                        ->where('purchase_order_payments.paid_from_slug','cash')
                        ->where('purchase_requests.project_site_id',$projectSiteId)
                        ->whereMonth('purchase_order_payments.created_at', $request['month'])->whereYear('purchase_order_payments.created_at', $request['year'])->orderBy('purchase_order_payments.created_at','desc')
                        ->select('purchase_order_payments.id as payment_id','purchase_order_payments.amount as amount'
                            ,'purchase_order_payments.created_at as date','purchase_requests.project_site_id as project_site_id'
                            ,'vendors.company as name')->get();

                    $subcontractorAdvancePayments = SubcontractorAdvancePayment::join('subcontractor','subcontractor.id','=','subcontractor_advance_payments.subcontractor_id')
                        ->where('subcontractor_advance_payments.paid_from_slug','cash')
                        ->where('subcontractor_advance_payments.project_site_id',$projectSiteId)
                        ->whereMonth('subcontractor_advance_payments.created_at', $request['month'])->whereYear('subcontractor_advance_payments.created_at', $request['year'])->orderBy('subcontractor_advance_payments.created_at','desc')
                        ->select('subcontractor_advance_payments.id as payment_id','subcontractor_advance_payments.amount as amount'
                            ,'subcontractor_advance_payments.project_site_id as project_site_id'
                            ,'subcontractor_advance_payments.created_at as date'
                            ,'subcontractor.company_name as name')->get();

                    $projectSiteAdvancePayments = ProjectSiteAdvancePayment::join('project_sites','project_sites.id','=','project_site_advance_payments.project_site_id')
                        ->where('project_site_advance_payments.paid_from_slug','cash')
                        ->where('project_site_advance_payments.project_site_id',$projectSiteId)
                        ->whereMonth('project_site_advance_payments.created_at', $request['month'])->whereYear('project_site_advance_payments.created_at', $request['year'])->orderBy('project_site_advance_payments.created_at','desc')
                        ->select('project_site_advance_payments.id as payment_id'
                            ,'project_site_advance_payments.amount as amount'
                            ,'project_site_advance_payments.created_at as date'
                            ,'project_site_advance_payments.project_site_id as project_site_id'
                            ,'project_sites.name as name')->get();

                    $siteTransferPayments = SiteTransferBillPayment::join('site_transfer_bills','site_transfer_bills.id','=','site_transfer_bill_payments.site_transfer_bill_id')
                        ->join('inventory_component_transfers','inventory_component_transfers.id','=','site_transfer_bills.inventory_component_transfer_id')
                        ->join('vendors','vendors.id','=','inventory_component_transfers.vendor_id')
                        ->join('inventory_components','inventory_components.id','inventory_component_transfers.inventory_component_id')
                        ->where('site_transfer_bill_payments.paid_from_slug','cash')
                        ->where('inventory_components.project_site_id',$projectSiteId)
                        ->whereMonth('site_transfer_bill_payments.created_at', $request['month'])->whereYear('site_transfer_bill_payments.created_at', $request['year'])->orderBy('site_transfer_bill_payments.created_at','desc')
                        ->select('site_transfer_bill_payments.id as payment_id','site_transfer_bill_payments.amount as amount'
                            ,'site_transfer_bill_payments.created_at as date'
                            ,'inventory_components.project_site_id as project_site_id'
                            ,'vendors.company as name')->get();

                    $assetMaintenancePayments = AssetMaintenanceBillPayment::join('asset_maintenance_bills','asset_maintenance_bills.id','asset_maintenance_bill_payments.asset_maintenance_bill_id')
                        ->join('asset_maintenance','asset_maintenance.id','=','asset_maintenance_bills.asset_maintenance_id')
                        ->join('asset_maintenance_vendor_relation','asset_maintenance_vendor_relation.asset_maintenance_id','=','asset_maintenance.id')
                        ->where('asset_maintenance_vendor_relation.is_approved',true)
                        ->join('vendors','vendors.id','=','asset_maintenance_vendor_relation.vendor_id')
                        ->where('asset_maintenance.project_site_id',$projectSiteId)
                        ->where('asset_maintenance_bill_payments.paid_from_slug','cash')
                        ->whereMonth('asset_maintenance_bill_payments.created_at', $request['month'])->whereYear('asset_maintenance_bill_payments.created_at', $request['year'])->orderBy('asset_maintenance_bill_payments.created_at','desc')
                        ->select('asset_maintenance_bill_payments.id as payment_id','asset_maintenance_bill_payments.amount as amount'
                            ,'asset_maintenance_bill_payments.created_at as date'
                            ,'asset_maintenance.project_site_id as project_site_id'
                            ,'vendors.company as name')->get();


                    $subcontractorCashBillTransactions = SubcontractorBillTransaction::join('subcontractor_bills','subcontractor_bills.id','=','subcontractor_bill_transactions.subcontractor_bills_id')
                        ->join('subcontractor_structure','subcontractor_structure.id','=','subcontractor_bills.sc_structure_id')
                        ->where('subcontractor_structure.project_site_id',$projectSiteId)
                        ->where('subcontractor_bill_transactions.paid_from_slug','cash')
                        ->join('subcontractor','subcontractor.id','=','subcontractor_structure.subcontractor_id')
                        ->whereMonth('subcontractor_bill_transactions.created_at', $request['month'])->whereYear('subcontractor_bill_transactions.created_at', $request['year'])->orderBy('subcontractor_bill_transactions.created_at','desc')
                        ->select('subcontractor_bill_transactions.id as payment_id','subcontractor_bill_transactions.subtotal as amount'
                            ,'subcontractor_bill_transactions.created_at as date'
                            ,'subcontractor_structure.project_site_id as project_site_id'
                            ,'subcontractor.company_name as name')->get();

                    $salesBillReconcileCash = BillReconcileTransaction::join('bills','bills.id','=','bill_reconcile_transactions.bill_id')
                        ->join('quotations','quotations.id','=','bills.quotation_id')
                        ->join('project_sites','project_sites.id','=','quotations.project_site_id')
                        ->where('quotations.project_site_id',$projectSiteId)
                        ->where('bill_reconcile_transactions.paid_from_slug','cash')
                        ->whereMonth('bill_reconcile_transactions.created_at', $request['month'])->whereYear('bill_reconcile_transactions.created_at', $request['year'])->orderBy('bill_reconcile_transactions.created_at','desc')
                        ->select('bill_reconcile_transactions.id as payment_id','bill_reconcile_transactions.amount as amount'
                            ,'bill_reconcile_transactions.created_at as date'
                            ,'quotations.project_site_id as project_site_id'
                            ,'project_sites.name as name')->get();

                    $subcontractorBillReconcileCash = SubcontractorBillReconcileTransaction::join('subcontractor_bills','subcontractor_bills.id','=','subcontractor_bill_reconcile_transactions.subcontractor_bill_id')
                        ->join('subcontractor_structure','subcontractor_structure.id','=','subcontractor_bills.sc_structure_id')
                        ->where('subcontractor_structure.project_site_id',$projectSiteId)
                        ->where('subcontractor_bill_reconcile_transactions.paid_from_slug','cash')
                        ->join('subcontractor','subcontractor.id','=','subcontractor_structure.subcontractor_id')
                        ->whereMonth('subcontractor_bill_reconcile_transactions.created_at', $request['month'])->whereYear('subcontractor_bill_reconcile_transactions.created_at', $request['year'])->orderBy('subcontractor_bill_reconcile_transactions.created_at','desc')
                        ->select('subcontractor_bill_reconcile_transactions.id as payment_id','subcontractor_bill_reconcile_transactions.amount as amount'
                            ,'subcontractor_bill_reconcile_transactions.created_at as date'
                            ,'subcontractor_structure.project_site_id as project_site_id'
                            ,'subcontractor.company_name as name')->get();

                    $indirectCashPayments = ProjectSiteIndirectExpense::join('project_sites','project_sites.id','=','project_site_indirect_expenses.project_site_id')
                        ->where('project_site_indirect_expenses.project_site_id',$projectSiteId)
                        ->where('project_site_indirect_expenses.paid_from_slug','cash')
                        ->groupBy('project_site_indirect_expenses.id')
                        ->groupBy('project_sites.id')
                        ->whereMonth('project_site_indirect_expenses.created_at', $request['month'])->whereYear('project_site_indirect_expenses.created_at', $request['year'])->orderBy('project_site_indirect_expenses.created_at','desc')
                        ->select('project_site_indirect_expenses.id as payment_id'
                            ,DB::raw("sum(project_site_indirect_expenses.gst + project_site_indirect_expenses.tds) AS amount")
                            ,'project_site_indirect_expenses.created_at as date'
                            ,'project_site_indirect_expenses.project_site_id as project_site_id'
                            ,'project_sites.name as name')->get();

                    $cashPaymentData = new Collection();
                    $cashPaymentData = $cashPaymentData->merge($purchaseOrderAdvancePayments)->merge($purchaseOrderBillPayments)
                                                        ->merge($subcontractorAdvancePayments)->merge($projectSiteAdvancePayments)
                                                        ->merge($siteTransferPayments)->merge($assetMaintenancePayments)
                                                        ->merge($subcontractorCashBillTransactions)->merge($salesBillReconcileCash)
                                                        ->merge($subcontractorBillReconcileCash)->merge($indirectCashPayments);

                    $transactionsData = new Collection();
                    foreach($purchaseTransactionData as $collection) {
                        $transactionsData->push($collection);
                    }
                    foreach($salaryTransactionData as $collection) {
                        $transactionsData->push($collection);
                    }
                    foreach($cashPaymentData as $collection){
                        $transactionsData->push($collection);
                    }
                    $dataWiseTransactionsData = $transactionsData->sortByDesc('date')->groupBy(function($transactionsData) {
                        return Carbon::parse($transactionsData->date)->format('l, d F Y');
                    });
                    $iterator = 0;
                    foreach($dataWiseTransactionsData as $date => $dateWiseTransactionData){
                        $listingData[$iterator]['date'] = $date;
                        $listingData[$iterator]['transaction_list'] = array();
                        $jIterator = 0;
                        foreach($dateWiseTransactionData as $transactionData){
                            if(array_key_exists('purchase_id',$transactionData->toArray())){

                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_id'] = $transactionData['purchase_id'];
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_type'] = $transactionData->peticashTransactionType->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['name'] = $transactionData->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['payment_status'] = $transactionData->peticashStatus->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_amount'] = $transactionData['bill_amount'];
                            }elseif(array_key_exists('salary_id',$transactionData->toArray())){
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_id'] = $transactionData['salary_id'];
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_type'] = $transactionData->peticashTransactionType->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['name'] = $transactionData->employee->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['payment_status'] = $transactionData->peticashStatus->name;
    				            if ($transactionData->peticashTransactionType->slug == 'salary') {	//salary
	                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_amount'] = $transactionData['payable_amount'];
				                }else{
				                    $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_amount'] = $transactionData['amount'];
				                }
                            }else{
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_id'] = 0;
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_type'] = "Cash";
                                $listingData[$iterator]['transaction_list'][$jIterator]['name'] = $transactionData['name'];
                                $listingData[$iterator]['transaction_list'][$jIterator]['payment_status'] = 'Approved';
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_amount'] = $transactionData['amount'];
                            }
                            $jIterator++;
                        }
                        $iterator++;
                    }
                    break;

                case 'salary' :
                    $salaryTransactionData = PeticashSalaryTransaction::where('project_site_id',$request['project_site_id'])->whereMonth('date', $request['month'])->whereYear('date', $request['year'])->orderBy('date','desc')->get();
                    $dataWiseSalaryTransactionData = $salaryTransactionData->groupBy('date');
                    $iterator = 0;
                    foreach($dataWiseSalaryTransactionData as $date => $salaryTransactionData){
                        $listingData[$iterator]['date'] = date('l, d F Y',strtotime($date));
                        $listingData[$iterator]['transaction_list'] = array();
                        $jIterator = 0;
                        foreach($salaryTransactionData as $transactionData){
                            $listingData[$iterator]['transaction_list'][$jIterator]['peticash_salary_transaction_id'] = $transactionData['id'];
                            $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_type'] = $transactionData->peticashTransactionType->name;
                            $listingData[$iterator]['transaction_list'][$jIterator]['employee_name'] = $transactionData->employee->name;
                            $listingData[$iterator]['transaction_list'][$jIterator]['payment_status'] = $transactionData->peticashStatus->name;
                    	    if ($transactionData->peticashTransactionType->slug == 'salary') { //salary
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_amount'] = $transactionData['payable_amount'];
                            }else{
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_amount'] = $transactionData['amount'];
                            }    
                			$jIterator++;
                          }
                        $iterator++;
                    }
                    break;

                case 'purchase' :
                    $purchaseTransactionData = PurchasePeticashTransaction::where('project_site_id',$request['project_site_id'])->whereMonth('date', $request['month'])->whereYear('date', $request['year'])->orderBy('date','desc')->get();
                    $dataWiseTransactionsData = $purchaseTransactionData->groupBy('date');
                    $iterator = 0;
                    foreach($dataWiseTransactionsData as $date => $dateWiseTransactionData){
                        $listingData[$iterator]['date'] = date('l, d F Y',strtotime($date));
                        $listingData[$iterator]['transaction_list'] = array();
                        $jIterator = 0;
                        foreach($dateWiseTransactionData as $transactionData){
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_id'] = $transactionData['id'];
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_type'] = $transactionData->peticashTransactionType->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['name'] = $transactionData->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['payment_status'] = $transactionData->peticashStatus->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_amount'] = $transactionData['bill_amount'];
                            $jIterator++;
                        }
                        $iterator++;
                    }
                    break;

                case 'cash' :
                    $projectSiteId = $request['project_site_id'];
                    $purchaseOrderAdvancePayments = PurchaseOrderAdvancePayment::join('purchase_orders','purchase_orders.id','purchase_order_advance_payments.purchase_order_id')
                        ->join('vendors','vendors.id','=','purchase_orders.vendor_id')
                        ->join('purchase_requests','purchase_requests.id','=','purchase_orders.purchase_request_id')
                        ->where('purchase_order_advance_payments.paid_from_slug','cash')
                        ->where('purchase_requests.project_site_id',$projectSiteId)
                        ->whereMonth('purchase_order_advance_payments.created_at', $request['month'])->whereYear('purchase_order_advance_payments.created_at', $request['year'])->orderBy('purchase_order_advance_payments.created_at','desc')
                        ->select('purchase_order_advance_payments.id as payment_id','purchase_order_advance_payments.amount as amount'
                            ,'purchase_order_advance_payments.created_at as created_at','purchase_requests.project_site_id as project_site_id'
                            ,'vendors.company as name')->get()->toArray();

                    $purchaseOrderBillPayments = PurchaseOrderPayment::join('purchase_order_bills','purchase_order_bills.id','=','purchase_order_payments.purchase_order_bill_id')
                        ->join('purchase_orders','purchase_orders.id','=','purchase_order_bills.purchase_order_id')
                        ->join('vendors','vendors.id','=','purchase_orders.vendor_id')
                        ->join('purchase_requests','purchase_requests.id','=','purchase_orders.purchase_request_id')
                        ->where('purchase_order_payments.paid_from_slug','cash')
                        ->where('purchase_requests.project_site_id',$projectSiteId)
                        ->whereMonth('purchase_order_payments.created_at', $request['month'])->whereYear('purchase_order_payments.created_at', $request['year'])->orderBy('purchase_order_payments.created_at','desc')
                        ->select('purchase_order_payments.id as payment_id','purchase_order_payments.amount as amount'
                            ,'purchase_order_payments.created_at as created_at','purchase_requests.project_site_id as project_site_id'
                            ,'vendors.company as name')->get()->toArray();

                    $subcontractorAdvancePayments = SubcontractorAdvancePayment::join('subcontractor','subcontractor.id','=','subcontractor_advance_payments.subcontractor_id')
                        ->where('subcontractor_advance_payments.paid_from_slug','cash')
                        ->where('subcontractor_advance_payments.project_site_id',$projectSiteId)
                        ->whereMonth('subcontractor_advance_payments.created_at', $request['month'])->whereYear('subcontractor_advance_payments.created_at', $request['year'])->orderBy('subcontractor_advance_payments.created_at','desc')
                        ->select('subcontractor_advance_payments.id as payment_id','subcontractor_advance_payments.amount as amount'
                            ,'subcontractor_advance_payments.project_site_id as project_site_id'
                            ,'subcontractor_advance_payments.created_at as created_at'
                            ,'subcontractor.company_name as name')->get()->toArray();

                    $projectSiteAdvancePayments = ProjectSiteAdvancePayment::join('project_sites','project_sites.id','=','project_site_advance_payments.project_site_id')
                        ->where('project_site_advance_payments.paid_from_slug','cash')
                        ->where('project_site_advance_payments.project_site_id',$projectSiteId)
                        ->whereMonth('project_site_advance_payments.created_at', $request['month'])->whereYear('project_site_advance_payments.created_at', $request['year'])->orderBy('project_site_advance_payments.created_at','desc')
                        ->select('project_site_advance_payments.id as payment_id'
                            ,'project_site_advance_payments.amount as amount'
                            ,'project_site_advance_payments.created_at as created_at'
                            ,'project_site_advance_payments.project_site_id as project_site_id'
                            ,'project_sites.name as name')->get()->toArray();

                    $siteTransferPayments = SiteTransferBillPayment::join('site_transfer_bills','site_transfer_bills.id','=','site_transfer_bill_payments.site_transfer_bill_id')
                        ->join('inventory_component_transfers','inventory_component_transfers.id','=','site_transfer_bills.inventory_component_transfer_id')
                        ->join('vendors','vendors.id','=','inventory_component_transfers.vendor_id')
                        ->join('inventory_components','inventory_components.id','inventory_component_transfers.inventory_component_id')
                        ->where('site_transfer_bill_payments.paid_from_slug','cash')
                        ->where('inventory_components.project_site_id',$projectSiteId)
                        ->whereMonth('site_transfer_bill_payments.created_at', $request['month'])->whereYear('site_transfer_bill_payments.created_at', $request['year'])->orderBy('site_transfer_bill_payments.created_at','desc')
                        ->select('site_transfer_bill_payments.id as payment_id','site_transfer_bill_payments.amount as amount'
                            ,'site_transfer_bill_payments.created_at as created_at'
                            ,'inventory_components.project_site_id as project_site_id'
                            ,'vendors.company as name')->get()->toArray();

                    $assetMaintenancePayments = AssetMaintenanceBillPayment::join('asset_maintenance_bills','asset_maintenance_bills.id','asset_maintenance_bill_payments.asset_maintenance_bill_id')
                        ->join('asset_maintenance','asset_maintenance.id','=','asset_maintenance_bills.asset_maintenance_id')
                        ->join('asset_maintenance_vendor_relation','asset_maintenance_vendor_relation.asset_maintenance_id','=','asset_maintenance.id')
                        ->where('asset_maintenance_vendor_relation.is_approved',true)
                        ->join('vendors','vendors.id','=','asset_maintenance_vendor_relation.vendor_id')
                        ->where('asset_maintenance.project_site_id',$projectSiteId)
                        ->where('asset_maintenance_bill_payments.paid_from_slug','cash')
                        ->whereMonth('asset_maintenance_bill_payments.created_at', $request['month'])->whereYear('asset_maintenance_bill_payments.created_at', $request['year'])->orderBy('asset_maintenance_bill_payments.created_at','desc')
                        ->select('asset_maintenance_bill_payments.id as payment_id','asset_maintenance_bill_payments.amount as amount'
                            ,'asset_maintenance_bill_payments.created_at as created_at'
                            ,'asset_maintenance.project_site_id as project_site_id'
                            ,'vendors.company as name')->get()->toArray();


                    $subcontractorCashBillTransactions = SubcontractorBillTransaction::join('subcontractor_bills','subcontractor_bills.id','=','subcontractor_bill_transactions.subcontractor_bills_id')
                        ->join('subcontractor_structure','subcontractor_structure.id','=','subcontractor_bills.sc_structure_id')
                        ->where('subcontractor_structure.project_site_id',$projectSiteId)
                        ->where('subcontractor_bill_transactions.paid_from_slug','cash')
                        ->join('subcontractor','subcontractor.id','=','subcontractor_structure.subcontractor_id')
                        ->whereMonth('subcontractor_bill_transactions.created_at', $request['month'])->whereYear('subcontractor_bill_transactions.created_at', $request['year'])->orderBy('subcontractor_bill_transactions.created_at','desc')
                        ->select('subcontractor_bill_transactions.id as payment_id','subcontractor_bill_transactions.subtotal as amount'
                            ,'subcontractor_bill_transactions.created_at as created_at'
                            ,'subcontractor_structure.project_site_id as project_site_id'
                            ,'subcontractor.company_name as name')->get()->toArray();

                    $salesBillReconcileCash = BillReconcileTransaction::join('bills','bills.id','=','bill_reconcile_transactions.bill_id')
                        ->join('quotations','quotations.id','=','bills.quotation_id')
                        ->join('project_sites','project_sites.id','=','quotations.project_site_id')
                        ->where('quotations.project_site_id',$projectSiteId)
                        ->where('bill_reconcile_transactions.paid_from_slug','cash')
                        ->whereMonth('bill_reconcile_transactions.created_at', $request['month'])->whereYear('bill_reconcile_transactions.created_at', $request['year'])->orderBy('bill_reconcile_transactions.created_at','desc')
                        ->select('bill_reconcile_transactions.id as payment_id','bill_reconcile_transactions.amount as amount'
                            ,'bill_reconcile_transactions.created_at as created_at'
                            ,'quotations.project_site_id as project_site_id'
                            ,'project_sites.name as name')->get()->toArray();

                    $subcontractorBillReconcileCash = SubcontractorBillReconcileTransaction::join('subcontractor_bills','subcontractor_bills.id','=','subcontractor_bill_reconcile_transactions.subcontractor_bill_id')
                        ->join('subcontractor_structure','subcontractor_structure.id','=','subcontractor_bills.sc_structure_id')
                        ->where('subcontractor_structure.project_site_id',$projectSiteId)
                        ->where('subcontractor_bill_reconcile_transactions.paid_from_slug','cash')
                        ->join('subcontractor','subcontractor.id','=','subcontractor_structure.subcontractor_id')
                        ->whereMonth('subcontractor_bill_reconcile_transactions.created_at', $request['month'])->whereYear('subcontractor_bill_reconcile_transactions.created_at', $request['year'])->orderBy('subcontractor_bill_reconcile_transactions.created_at','desc')
                        ->select('subcontractor_bill_reconcile_transactions.id as payment_id','subcontractor_bill_reconcile_transactions.amount as amount'
                            ,'subcontractor_bill_reconcile_transactions.created_at as created_at'
                            ,'subcontractor_structure.project_site_id as project_site_id'
                            ,'subcontractor.company_name as name')->get()->toArray();

                    $indirectCashPayments = ProjectSiteIndirectExpense::join('project_sites','project_sites.id','=','project_site_indirect_expenses.project_site_id')
                        ->where('project_site_indirect_expenses.project_site_id',$projectSiteId)
                        ->where('project_site_indirect_expenses.paid_from_slug','cash')
                        ->groupBy('project_site_indirect_expenses.id')
                        ->groupBy('project_sites.id')
                        ->whereMonth('project_site_indirect_expenses.created_at', $request['month'])->whereYear('project_site_indirect_expenses.created_at', $request['year'])->orderBy('project_site_indirect_expenses.created_at','desc')
                        ->select('project_site_indirect_expenses.id as payment_id'
                            ,DB::raw("sum(project_site_indirect_expenses.gst + project_site_indirect_expenses.tds) AS amount")
                            ,'project_site_indirect_expenses.created_at as created_at'
                            ,'project_site_indirect_expenses.project_site_id as project_site_id'
                            ,'project_sites.name as name')->get()->toArray();

                    $cashPaymentData = array_merge($purchaseOrderAdvancePayments,$purchaseOrderBillPayments,$subcontractorAdvancePayments,$projectSiteAdvancePayments,$siteTransferPayments,$assetMaintenancePayments,$subcontractorCashBillTransactions,$salesBillReconcileCash,$subcontractorBillReconcileCash,$indirectCashPayments);
                    $transactionsData = new Collection();
                    foreach($cashPaymentData as $collection) {
                        $transactionsData->push($collection);
                    }
                    $dataWiseTransactionsData = $transactionsData->sortByDesc('created_at')->groupBy('created_at');
                    $iterator = 0;
                    foreach($dataWiseTransactionsData as $date => $cashTransactionData){
                        $listingData[$iterator]['date'] = date('l, d F Y',strtotime($date));
                        $listingData[$iterator]['transaction_list'] = array();
                        $jIterator = 0;
                        foreach($cashTransactionData as $transactionData){
                            $listingData[$iterator]['transaction_list'][$jIterator]['peticash_salary_transaction_id'] = 0;
                            $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_type'] = 'cash';
                            $listingData[$iterator]['transaction_list'][$jIterator]['employee_name'] = $transactionData['name'];
                            $listingData[$iterator]['transaction_list'][$jIterator]['payment_status'] = 'Approved';
                            $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_amount'] = $transactionData['amount'];
                            $jIterator++;
                        }
                        $iterator++;
                    }
                    break;
            }
            $pageId = $request['page'];
            $displayLength = 30;
            $start = ((int)$pageId) * $displayLength;
            $totalSent = ($pageId + 1) * $displayLength;
            $totalTransactionCount = count($listingData);
            $remainingCount = $totalTransactionCount - $totalSent;
            $data['transactions_list'] = array();
            for($iterator = $start,$jIterator = 0; $iterator < $totalTransactionCount && $jIterator < $displayLength; $iterator++,$jIterator++){
                $data['transactions_list'][] = $listingData[$iterator];
            }
            if($remainingCount > 0 ){
                $page_id = (string)($pageId + 1);
            }else{
                $page_id = "";
            }
            $date = date('d M Y',strtotime(Carbon::now()));
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $page_id = '';
            $date = date('d M Y',strtotime(Carbon::now()));
            $data = [
                'action' => 'Get Salary Listing',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
            'data' => $data,
            'date' => $date,
            'page_id' => $page_id
        ];
        return response()->json($response,$status);
    }

    public function getTransactionDetails(Request $request){
        try{
            $salaryTransactionData = PeticashSalaryTransaction::where('id',$request['peticash_transaction_id'])->first();
            $data['peticash_transaction_id'] = $salaryTransactionData->id;
            $data['employee_name'] = $salaryTransactionData->employee->name;
            $data['per_day_wages'] = $salaryTransactionData->employee->per_day_wages;
            $data['project_site_name'] = $salaryTransactionData->projectSite->name;
            $data['amount'] = $salaryTransactionData->amount;
            $data['payable_amount'] = ($salaryTransactionData->payable_amount) ? $salaryTransactionData->payable_amount : '';
            $data['reference_user_name'] = $salaryTransactionData->referenceUser->first_name.' '.$salaryTransactionData->referenceUser->last_name;
            $data['date'] = date('l, d F Y',strtotime($salaryTransactionData->date));
            $data['days'] = $salaryTransactionData->days;
            $data['remark'] = $salaryTransactionData->remark;
            $data['admin_remark'] = ($salaryTransactionData->admin_remark == null) ? '' : $salaryTransactionData->admin_remark;
            $data['peticash_transaction_type'] = $salaryTransactionData->peticashTransactionType->name;
            $data['peticash_status_name'] = $salaryTransactionData->peticashStatus->name;
            $data['tds'] = $salaryTransactionData['tds'];
            $data['pf'] = $salaryTransactionData['pf'];
            $data['pt'] = $salaryTransactionData['pt'];
            $data['esic'] = $salaryTransactionData['esic'];
	        if($salaryTransactionData['payment_type_id'] != null){
                $data['payment_type'] = $salaryTransactionData->paymentType->name;
            }else{
                $data['payment_type'] = '';
            }
            if($salaryTransactionData['bank_id'] != null){
                $data['bank_name'] = BankInfo::where('id',$salaryTransactionData['bank_id'])->pluck('bank_name')->first();
            }else{
                $data['bank_name'] = '';
            }
            $transactionImages = PeticashSalaryTransactionImages::where('peticash_salary_transaction_id',$request['peticash_transaction_id'])->get();
            if(count($transactionImages) > 0){
                $data['list_of_images'] = $this->getUploadedImages($transactionImages,$request['peticash_transaction_id']);
            }else{
                $data['list_of_images'][0]['image_url'] = null;
            }
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
            $message = $e->getMessage();
            $status = 500;
            $data = [
                'action' => 'Get Transaction Detail',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
            'data' => $data
        ];
        return response()->json($response,$status);
    }

    public function getUploadedImages($transactionImages,$transactionId){
        $iterator = 0;
        $images = array();
        $sha1SalaryTransactionId = sha1($transactionId);
        $imageUploadPath = env('PETICASH_SALARY_TRANSACTION_IMAGE_UPLOAD').$sha1SalaryTransactionId;
        foreach($transactionImages as $index => $image){
            $images[$iterator]['image_url'] = $imageUploadPath.DIRECTORY_SEPARATOR.$image->name;
            $iterator++;
        }
        return $images;
    }

    public function getStatistics(Request $request){
        try{
            $message = 'Success';
            $status = 200;
            $data = array();
            $projectSiteAdvancedAmount = ProjectSiteAdvancePayment::where('project_site_id',$request['project_site_id'])->where('paid_from_slug','cash')->sum('amount');
            $salesBillCashAmount = BillReconcileTransaction::join('bills','bills.id','=','bill_reconcile_transactions.bill_id')
                ->join('quotations','quotations.id','=','bills.quotation_id')
                ->where('quotations.project_site_id', $request['project_site_id'])
                ->where('bill_reconcile_transactions.paid_from_slug','cash')
                ->sum('bill_reconcile_transactions.amount');
            $salesBillTransactions = BillTransaction::join('bills','bills.id','=','bill_transactions.bill_id')
                ->join('quotations','quotations.id','=','bills.quotation_id')
                ->where('quotations.project_site_id', $request['project_site_id'])
                ->where('bill_transactions.paid_from_slug','cash')
                ->sum('total');
            $approvedPeticashStatusId = PeticashStatus::where('slug','approved')->pluck('id')->first();
            $allocatedAmount  = PeticashSiteTransfer::where('project_site_id',$request['project_site_id'])->sum('amount');
            $data['total_salary_amount'] = round(PeticashSalaryTransaction::where('peticash_transaction_type_id',PeticashTransactionType::where('slug','salary')->pluck('id')->first())
                                            ->where('project_site_id',$request['project_site_id'])
                                            ->where('peticash_status_id',$approvedPeticashStatusId)
                                            ->sum('payable_amount'),2);
            $data['total_advance_amount'] = round(PeticashSalaryTransaction::where('peticash_transaction_type_id',PeticashTransactionType::where('slug','advance')->pluck('id')->first())
                                                ->where('project_site_id',$request['project_site_id'])
                                                ->where('peticash_status_id',$approvedPeticashStatusId)
                                                ->sum('amount'),2);
            $data['total_purchase_amount'] = round(PurchasePeticashTransaction::whereIn('peticash_transaction_type_id', PeticashTransactionType::where('type','PURCHASE')->pluck('id'))
                                                ->where('project_site_id',$request['project_site_id'])
                                                ->where('peticash_status_id',$approvedPeticashStatusId)
                                                ->sum('bill_amount'), 2);


            $cashPurchaseOrderAdvancePaymentTotal = PurchaseOrderAdvancePayment::join('purchase_orders','purchase_orders.id','=','purchase_order_advance_payments.purchase_order_id')
                ->join('purchase_requests','purchase_requests.id','=','purchase_orders.purchase_request_id')
                ->where('purchase_order_advance_payments.paid_from_slug','cash')
                ->where('purchase_requests.project_site_id',$request['project_site_id'])
                ->sum('amount');

            $cashSubcontractorAdvancePaymentTotal = SubcontractorAdvancePayment::where('subcontractor_advance_payments.paid_from_slug','cash')
                                ->where('project_site_id',$request['project_site_id'])->sum('amount');

            $cashSubcontractorBillTransactionTotal = SubcontractorBillTransaction::join('subcontractor_bills','subcontractor_bills.id','=','subcontractor_bill_transactions.subcontractor_bills_id')
                ->join('subcontractor_structure','subcontractor_structure.id','=','subcontractor_bills.sc_structure_id')
                ->where('subcontractor_structure.project_site_id',$request['project_site_id'])
                ->where('subcontractor_bill_transactions.paid_from_slug','cash')->sum('subcontractor_bill_transactions.subtotal');

            $subcontractorBillReconcile = SubcontractorBillReconcileTransaction::join('subcontractor_bills','subcontractor_bills.id','=','subcontractor_bill_reconcile_transactions.subcontractor_bill_id')
                ->join('subcontractor_structure','subcontractor_structure.id','=','subcontractor_bills.sc_structure_id')
                ->where('subcontractor_structure.project_site_id',$request['project_site_id'])
                ->where('subcontractor_bill_reconcile_transactions.paid_from_slug','cash')
                ->sum('subcontractor_bill_reconcile_transactions.amount');

            $siteTransferCashAmount = SiteTransferBillPayment::join('site_transfer_bills','site_transfer_bills.id','=','site_transfer_bill_payments.site_transfer_bill_id')
                ->join('inventory_component_transfers','inventory_component_transfers.id','=','site_transfer_bills.inventory_component_transfer_id')
                ->join('inventory_components','inventory_components.id','inventory_component_transfers.inventory_component_id')
                ->where('site_transfer_bill_payments.paid_from_slug','cash')
                ->where('inventory_components.project_site_id',$request['project_site_id'])
                ->sum('site_transfer_bill_payments.amount');

            $assetMaintenanceCashAmount = AssetMaintenanceBillPayment::join('asset_maintenance_bills','asset_maintenance_bills.id','asset_maintenance_bill_payments.asset_maintenance_bill_id')
                ->join('asset_maintenance','asset_maintenance.id','=','asset_maintenance_bills.asset_maintenance_id')
                ->where('asset_maintenance.project_site_id',$request['project_site_id'])
                ->where('asset_maintenance_bill_payments.paid_from_slug','cash')
                ->sum('asset_maintenance_bill_payments.amount');

            $indirectGSTCashAmount = ProjectSiteIndirectExpense::where('project_site_id',$request['project_site_id'])
                ->where('paid_from_slug','cash')->sum('gst');

            $indirectTDSCashAmount = ProjectSiteIndirectExpense::where('project_site_id',$request['project_site_id'])
                ->where('paid_from_slug','cash')->sum('tds');

            $data['total_subcontractor_amount'] = round($cashSubcontractorBillTransactionTotal + $subcontractorBillReconcile + $cashSubcontractorAdvancePaymentTotal,2);

            $data['total_asset_amount'] = round($assetMaintenanceCashAmount,2);

            $data['remaining_amount'] = round(($allocatedAmount
                                                    - ($data['total_salary_amount'] + $data['total_advance_amount']
                                                        + $data['total_purchase_amount'] + $cashPurchaseOrderAdvancePaymentTotal
                                                         + $cashSubcontractorBillTransactionTotal
                                                        + $subcontractorBillReconcile + $siteTransferCashAmount + $assetMaintenanceCashAmount
                                                        + $indirectGSTCashAmount + $indirectTDSCashAmount + $cashSubcontractorAdvancePaymentTotal)),3);
            $data['allocated_amount'] = round($allocatedAmount,2);
        }catch(\Exception $e){
            $message = $e->getMessage();
            $status = 500;
            $data =[
                'action' => 'Get Peticash Statistics',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
        }
        $response = [
            'message' => $message,
            'data' => $data
        ];
        return response()->json($response,$status);
    }

    public function calculatePayableAmount(Request $request){
        try{
            $message = "Success";
            $status = 200;
            $data = array();
            if($request['type'] == 'salary'){
                $payable_amount = ($request['per_day_wages'] * $request['working_days']) + $request['balance'] - ($request['pt'] + $request['pf'] + $request['esic'] + $request['tds']);
                if($payable_amount < 0){
                    $data['payable_amount'] = '0';
                }else{
                    $data['payable_amount'] = (string)round($payable_amount,3);
                }
            }
            $transactionTypeId = PeticashTransactionType::where('slug',$request['type'])->pluck('id')->first();
           /* $lastRequestedSalary = PeticashRequestedSalaryTransaction::where('employee_id',$request['employee_id'])
                ->where('project_site_id',$request['project_site_id'])
                ->where('peticash_status_id',PeticashStatus::where('slug','approved')->pluck('id')->first())
                ->where('peticash_transaction_type_id',$transactionTypeId)
                ->select('amount','created_at')->get()->last();
           if($lastRequestedSalary != null){
                 $salaryTransactionAmountAfterLastRequest = PeticashSalaryTransaction::where('created_at','>=',$lastRequestedSalary['created_at'])
                     ->where('employee_id',$request['employee_id'])
                     ->where('project_site_id',$request['project_site_id'])
                     ->where('peticash_transaction_type_id',$transactionTypeId)->sum('amount');
                 $approvedAmount = ($salaryTransactionAmountAfterLastRequest < $lastRequestedSalary['amount']) ? ($lastRequestedSalary['amount'] - $salaryTransactionAmountAfterLastRequest) : 0;
             }else{
                 $approvedAmount = '0';
             }*/
            $requestedSalary = PeticashRequestedSalaryTransaction::where('employee_id',$request['employee_id'])
                                ->where('project_site_id',$request['project_site_id'])
                                ->where('peticash_status_id',PeticashStatus::where('slug','approved')->pluck('id')->first())
                                ->where('peticash_transaction_type_id',$transactionTypeId)
                                ->sum('amount');
            $lastRequestedSalary['amount'] = $requestedSalary;
            if($requestedSalary != null){
                $salaryTransactionAmountAfterLastRequest = PeticashSalaryTransaction::where('employee_id',$request['employee_id'])
                    ->where('project_site_id',$request['project_site_id'])
                    ->where('peticash_transaction_type_id',$transactionTypeId)->sum('amount');
                $approvedAmount = ($salaryTransactionAmountAfterLastRequest < $lastRequestedSalary['amount']) ? ($lastRequestedSalary['amount'] - $salaryTransactionAmountAfterLastRequest) : 0;
            }else{
                $approvedAmount = '0';
            }
            if($request->has('bank_id')){
                $bankApprovedAmount = BankInfo::where('id',$request['bank_id'])->pluck('balance_amount')->first();
                $data['approved_amount'] = ($bankApprovedAmount > $approvedAmount) ? (string)round($approvedAmount,3) : (string)round($bankApprovedAmount,3);
            }else{
                $data['approved_amount'] = (string)round($approvedAmount,3);
            }
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $data =[
                'action' => 'Calculate Payable Amount',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
        }
        $response = [
            'message' => $message,
            'data' => $data
        ];
        return response()->json($response,$status);
    }

    public function getPaymentVoucherPdf(Request $request){
        try{
            $salaryTransactionId = $request['peticash_transaction_id'];
            $peticashSalaryTransaction = PeticashSalaryTransaction::where('id',$salaryTransactionId)->first();
            $data['project_site'] = $peticashSalaryTransaction->projectSite->name;
            $data['date'] = date('d/m/Y',strtotime($peticashSalaryTransaction->date));
            $data['paid_to'] = $peticashSalaryTransaction->employee->name;
      	    $data['particulars'] = $peticashSalaryTransaction->remark;
            if ($peticashSalaryTransaction->peticash_transaction_type_id == 5) {
		$data['amount_in_words'] = ucwords(NumberHelper::getIndianCurrency($peticashSalaryTransaction->payable_amount));
	        $data['amount'] = $peticashSalaryTransaction->payable_amount;
 	    } else {
		$data['amount_in_words'] = ucwords(NumberHelper::getIndianCurrency($peticashSalaryTransaction->amount));
                $data['amount'] = $peticashSalaryTransaction->amount;

	    }
            $data['approved_by'] = $peticashSalaryTransaction->referenceUser->first_name.' '.$peticashSalaryTransaction->referenceUser->last_name;
            $file_name = 'PaymentVoucher-'.$salaryTransactionId.'.pdf';
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadView('peticash.peticash-management.salary.pdf.payment-voucher',$data);
            $pdfContent = $pdf->stream($file_name);
            $webUploadPath = env('WEB_PUBLIC_PATH');
            $pdfUploadPath = env('PAYMENT_VOUCHER_UPLOAD_PATH');
            $pdfUploadPath = $pdfUploadPath.sha1($salaryTransactionId);
            $paymentVoucherUploadPath = $pdfUploadPath;
            /* Create Upload Directory If Not Exists */
            if (!file_exists($webUploadPath.$paymentVoucherUploadPath)) {
                File::makeDirectory($webUploadPath.$paymentVoucherUploadPath, $mode = 0777, true, true);
            }
            file_put_contents($webUploadPath.$paymentVoucherUploadPath.'/'.$file_name,$pdfContent);
            $pdf_path = [
                'pdf_url' => $paymentVoucherUploadPath.'/'.$file_name
            ];
            $message = "PDF created successfully!";
            $status = 200;
        }catch(Exception $e){
            $status = 500;
            $message = "Fail";
            $pdf_path = '';
            $data = [
                'action' => 'Get Payment Voucher PDF',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message,
            'pdf_path' => $pdf_path
        ];

        return response()->json($response,$status);
    }

    public function deletePaymentVoucherPdf(Request $request){
        try{
            $ds = DIRECTORY_SEPARATOR;
            $webUploadPath = env('WEB_PUBLIC_PATH');
            $file_to_be_deleted = $webUploadPath.$ds.$request['pdf_path'];
            if(!file_exists($file_to_be_deleted)){
                $message = "File does not exists";
            }else{
                unlink($file_to_be_deleted);
                $message = "PDF deleted Successfully";
            }
            $status = 200;
        }catch(Exception $e){
            $message = "Fail";
            $status = 500;
            $data = [
                'action' => 'Delete Payment Voucher PDF',
                'exception' => $e->getMessage()
            ];
            Log::critical(json_encode($data));
        }
        $response = [
            'message' => $message
        ];
        return response()->json($response,$status);
    }
}

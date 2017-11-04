<?php

namespace App\Http\Controllers\Peticash;

use App\Employee;
use App\PaymentType;
use App\PeticashSalaryTransaction;
use App\PeticashSalaryTransactionImages;
use App\PeticashStatus;
use App\PeticashTransactionType;
use App\PurcahsePeticashTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $employeeDetails = Employee::where('name','ilike','%'.$request->employee_name.'%')->where('project_site_id',$request['project_site_id'])->get()->toArray();
            $data = array();
            foreach($employeeDetails as $key => $employeeDetail){
                $data[$iterator]['employee_id'] = $employeeDetail['id'];
                $data[$iterator]['format_employee_id'] = $employeeDetail['employee_id'];
                $data[$iterator]['employee_name'] = $employeeDetail['name'];
                $data[$iterator]['per_day_wages'] = (int)$employeeDetail['per_day_wages'];
                $data[$iterator]['employee_profile_picture'] = '/assets/global/img/logo.jpg';
                $peticashStatus = PeticashStatus::whereIn('slug',['approved','pending'])->select('id','slug')->get();
                $transactionPendingCount = PeticashSalaryTransaction::where('employee_id',$employeeDetail['id'])->where('peticash_status_id',$peticashStatus->where('slug','pending')->pluck('id')->first())->count();
                $data[$iterator]['is_transaction_pending'] = ($transactionPendingCount > 0) ? true : false;
                $salaryTransactions = PeticashSalaryTransaction::where('employee_id',$employeeDetail['id'])->where('peticash_status_id',$peticashStatus->where('slug','approved')->pluck('id')->first())->select('id','amount','payable_amount','peticash_transaction_type_id','created_at')->get();
                $paymentSlug = PeticashTransactionType::where('type','PAYMENT')->select('id','slug')->get();
                $advanceSalaryTotal = $salaryTransactions->where('peticash_transaction_type_id',$paymentSlug->where('slug','advance')->pluck('id')->first())->sum('amount');
                $actualSalaryTotal = $salaryTransactions->where('peticash_transaction_type_id',$paymentSlug->where('slug','salary')->pluck('id')->first())->sum('amount');
                $payableSalaryTotal = $salaryTransactions->sum('payable_amount');
                $data[$iterator]['balance'] = $actualSalaryTotal - $advanceSalaryTotal - $payableSalaryTotal;
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
            "data" => $data
        ];
        return response()->json($response,$status);
    }

    public function createSalary(Request $request){
        try{
            $user = Auth::user();
            $status = 200;
            $message = "Salary transaction created successfully";
            $salaryData = $request->except('token','images','type');
            $salaryData['reference_user_id'] = $user['id'];
            $salaryData['peticash_transaction_type_id'] = PeticashTransactionType::where('slug','ilike',$request['type'])->pluck('id')->first();
            $salaryData['payment_type_id'] = PaymentType::where('slug','peticash')->pluck('id')->first();
            $salaryData['peticash_status_id'] = PeticashStatus::where('slug','pending')->pluck('id')->first();
            $salaryData['created_at'] = $salaryData['updated_at'] = Carbon::now();
            $salaryTransactionId = PeticashSalaryTransaction::insertGetId($salaryData);
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

    public function getSalaryListing(Request $request){
        try{
            $listingData = array();
            switch ($request['type']){
                case 'both' :
                    $purchaseTrasactionData = PurcahsePeticashTransaction::where('project_site_id',$request['project_site_id'])->whereMonth('date', $request['month'])->whereYear('date', $request['year'])->orderBy('date','desc')->get();
                    $salaryTransactionData = PeticashSalaryTransaction::where('project_site_id',$request['project_site_id'])->whereMonth('date', $request['month'])->whereYear('date', $request['year'])->orderBy('date','desc')->get();
                    $transactionsData = $purchaseTrasactionData->merge($salaryTransactionData);
                    $dataWiseTransactionsData = $transactionsData->sortByDesc('date')->groupBy('date');
                    $iterator = 0;
                    $purchaseTransactionTypeIds = PeticashTransactionType::where('type','PURCHASE')->pluck('id')->toArray();
                    foreach($dataWiseTransactionsData as $date => $dateWiseTransactionData){
                        $listingData[$iterator]['date'] = date('l, d F Y',strtotime($date));
                        $listingData[$iterator]['transaction_list'] = array();
                        $jIterator = 0;
                        foreach($dateWiseTransactionData as $transactionData){
                            if(in_array($transactionData->peticash_transaction_type_id,$purchaseTransactionTypeIds)){
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_id'] = $transactionData['id'];
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_type'] = $transactionData->peticashTransactionType->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['name'] = $transactionData->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['payment_status'] = $transactionData->peticashStatus->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_amount'] = $transactionData['bill_amount'];
                            }else{
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_id'] = $transactionData['id'];
                                $listingData[$iterator]['transaction_list'][$jIterator]['peticash_transaction_type'] = $transactionData->peticashTransactionType->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['name'] = $transactionData->employee->name;
                                $listingData[$iterator]['transaction_list'][$jIterator]['payment_status'] = $transactionData->peticashStatus->name;
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
                            $listingData[$iterator]['transaction_list'][$jIterator]['peticash_salary_transaction_amount'] = $transactionData['amount'];
                            $jIterator++;
                        }
                        $iterator++;
                    }
                    break;

                case 'purchase' :
                    $purchaseTrasactionData = PurcahsePeticashTransaction::where('project_site_id',$request['project_site_id'])->whereMonth('date', $request['month'])->whereYear('date', $request['year'])->orderBy('date','desc')->get();
                    $dataWiseTransactionsData = $purchaseTrasactionData->groupBy('date');
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
            }



            $pageId = $request['page'];
            $displayLength = 10;
            $start = ((int)$pageId) * $displayLength;
            $totalSent = ($pageId + 1) * $displayLength;
            $totalTransactionCount = count($listingData);
            $remainingCount = $totalTransactionCount - $totalSent;
            $data['transaction_list'] = array();
            for($iterator = $start,$jIterator = 0; $iterator < $totalSent && $jIterator < $totalTransactionCount; $iterator++,$jIterator++){
                $data['transaction_list'][] = $listingData[$iterator];
            }
            if($remainingCount > 0 ){
                $page_id = (string)($pageId + 1);
            }else{
                $page_id = "";
            }
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
            $message = "Fail";
            $status = 500;
            $page_id = '';
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
            'page_id' => $page_id
        ];
        return response()->json($response,$status);
    }

    public function getTransactionDetails(Request $request){
        try{
            $salaryTransactionData = PeticashSalaryTransaction::where('id',$request['peticash_transaction_id'])->first();
            $data['peticash_transaction_id'] = $salaryTransactionData->id;
            $data['employee_name'] = $salaryTransactionData->employee->name;
            $data['project_site_name'] = $salaryTransactionData->projectSite->name;
            $data['amount'] = $salaryTransactionData->amount;
            $data['payable_amount'] = $salaryTransactionData->payable_amount;
            $data['reference_user_name'] = $salaryTransactionData->referenceUser->first_name.' '.$salaryTransactionData->referenceUser->last_name;
            $data['date'] = date('l, d F Y',strtotime($salaryTransactionData->date));
            $data['days'] = $salaryTransactionData->days;
            $data['remark'] = $salaryTransactionData->remark;
            $data['admin_remark'] = ($salaryTransactionData->admin_remark == null) ? '' : $salaryTransactionData->admin_remark;
            $data['peticash_transaction_type'] = $salaryTransactionData->peticashTransactionType->name;
            $data['peticash_status_name'] = $salaryTransactionData->peticashStatus->name;
            $data['payment_type'] = $salaryTransactionData->paymentType->name;
            $message = "Success";
            $status = 200;
        }catch(\Exception $e){
            $message = $e->getMessage();
            $status = 500;
            $data = [
                'action' => 'Get Transaction Listing',
                'exception' => $e->getMessage(),
                'params' => $request->all()
            ];
            Log::crtical(json_encode($data));
        }
        $response = [
            'message' => $message,
            'data' => $data
        ];
        return response()->json($response,$status);
    }
}

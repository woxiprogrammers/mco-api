<?php

namespace App\Http\Controllers\Peticash;

use App\Employee;
use App\PaymentType;
use App\PeticashSalaryTransaction;
use App\PeticashStatus;
use App\PeticashTransactionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                $approvedPeticashStatusId = PeticashStatus::where('slug','approved')->pluck('id')->first();
                $salaryTransactions = PeticashSalaryTransaction::where('employee_id',$employeeDetail['id'])->where('peticash_status_id',$approvedPeticashStatusId)->select('amount')->get();
                $data[$iterator]['total_amount_paid'] = $salaryTransactions->where('amount','>',0)->sum('amount');
                $data[$iterator]['extra_amount_paid'] = $salaryTransactions->where('amount','<',0)->sum('amount');
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
            $salaryData = $request->except('token');
            $salaryData['reference_user_id'] = $user['id'];
            $salaryData['peticash_transaction_type_id'] = PeticashTransactionType::where('slug','ilike',$request['type'])->pluck('id')->first();
            $salaryData['payment_type_id'] = PaymentType::where('slug','peticash')->pluck('id')->first();
            $salaryData['peticash_status_id'] = PeticashStatus::where('slug','pending')->pluck('id')->first();
            PeticashSalaryTransaction::create($salaryData);
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
}

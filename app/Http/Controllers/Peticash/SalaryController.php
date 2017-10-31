<?php

namespace App\Http\Controllers\Peticash;

use App\Employee;
use App\PaymentType;
use App\PeticashSalaryTransaction;
use App\PeticashTransactionType;
use App\Role;
use App\UserProjectSiteRelation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Mockery\Exception;
use Monolog\Handler\SyslogUdp\UdpSocket;

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
                $data[$iterator]['per_day_wages'] = $employeeDetail['per_day_wages'];
                $data[$iterator]['employee_profile_picture'] = '/assets/global/img/logo.jpg';
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
            $status = 200;
            $message = "Salary transaction created successfully";
            $salaryData = $request->except('token');
            $salaryData['reference_user_id'] = ;
            $salaryData['peticash_transaction_type_id'] = PeticashTransactionType::where('slug','ilike',$request['type'])->pluck('id')->first();
            $salaryData['payment_type_id'] = PaymentType::where('slug','peticash')->pluck('id')->first();
            PeticashSalaryTransaction::create($salaryData);
        }catch(\Exception $e){
            $status = 500;
            $message = /*"Fail"*/$e->getMessage();
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

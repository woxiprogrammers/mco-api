<?php

namespace App\Http\Controllers\Peticash;

use App\Employee;
use App\PeticashSalaryTransaction;
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
                $data[$iterator]['per_day_wages'] = $employeeDetail['per_day_wages'];
                $data[$iterator]['employee_profile_picture'] = '/assets/global/img/logo.jpg';
                $salaryTransactions = PeticashSalaryTransaction::where('employee_id',$employeeDetail['id'])->select('amount')->get();
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
}

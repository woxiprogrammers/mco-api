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
           // dd($request->all());
            $status = 200;
            $message = "Success";
            $employeeDetails = Employee::where('name','ilike','%'.$request->keyword.'%')->where('project_site_id',$request['project_site_id'])->get()->toArray();
            dd($employeeDetails);
            foreach($employeeDetails as $key => $employeeDetail){
                $data['employee_id'] = $employeeDetail['id'];
                $data['format_employee_id'] = $employeeDetail['employee_id'];
                $data['name'] = $employeeDetail['name'];
                $data['per_day_wages'] = $employeeDetail['per_day_wages'];
                $data['profile_picture'] = '/assets/global/img/logo.jpg';
                $totalAdvanced = PeticashSalaryTransaction::where('type','PAYMENT')->where('slug','Advance')->where('id',$employeeDetail['id'])->sum('amount');
                $lastActualSalary = PeticashSalaryTransaction::where('id',$employeeDetail['id'])->orderBy('id','asc')->pluck('amount')->first();
                if($lastActualSalary > 0){
                    $lastActualSalary = 0;
                }
            }
            $data = array();

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

<?php

namespace App\Http\Controllers\API;

use App\BankPayment;
use App\Config;
use App\Http\Controllers\Controller;
use App\PaymentType;
use App\Student;
use App\StudentPayment;
use App\Utility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api');
    }
    public function getStudent(Request $request)
    {
        // Validation rules
        $rules = [
            'matNo' => 'required|string|max:255',
        ];

        // Create a validator instance
        $validator = Validator::make($request->all(), $rules);

        // Check for validation failure
        if ($validator->fails()) {
            // Return a custom error response
            return response()->json([
                'responseCode' => 400,
                'responseMessage' => 'Validation errors occurred',
                'errors' => $validator->errors()
            ]);
        }

        try {
            $mat_no = $request['matNo'];
            $student = Student::where('mat_no', $mat_no)->first();
            if (!is_null($student)) {
                $data = [
                    'fullName' => $student->getName(),
                    'matNo' => $student->mat_no,
                    'programName' => $student->program->name,
                    'school' => $student->getSchool()
                ];
                return response()->json([
                    'responseCode' => 200,
                    'responseData' => $data,
                    'responseMessage' => "success",

                ]);
            } else {
                return response()->json([
                    'responseCode' => 400,
                    'responseMessage' => "no record",
                ]);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'responseCode' => 400,
                'responseMessage' => 'error'
            ]);
        }
    }

    public function getPaymentTypes()
    {
        try {
            $data = [];
            $payments = PaymentType::all();
            foreach ($payments as $payment) {
                $row['paymentCode'] = $payment->payment_code;
                $row['paymentName'] = $payment->name;
                $data[] = $row;
            }
            return response()->json([
                'responseCode' => 200,
                'responseData' => $data,
                'responseMessage' => 'success'
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'responseCode' => 400,
                'responseMessage' => 'error'
            ]);
        }
    }

    public function getCurrencies()
    {
        try {
            $data = [
                [
                    "currencyCode" => "GMD",
                    "currencyName" => "Dalasi",
                ],
                [
                    "currencyCode" => "USD",
                    "currencyName" => "Dollar",
                ],
                [
                    "currencyCode" => "EUR",
                    "currencyName" => "Euro",
                ],
                [
                    "currencyCode" => "CFA",
                    "currencyName" => "CFA",
                ]
            ];
            return response()->json([
                'responseCode' => 200,
                'responseData' => $data,
                'responseMessage' => 'success'
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'responseCode' => 400,
                'responseMessage' => 'error'
            ]);
        }
    }

    public function postPayment(Request $request)
    {
        // Validation rules
        $rules = [
            'matNo' => 'required|string|max:255',
            'paymentCode' => 'required|string|max:255',
            'currencyCode' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'receiptNo' => 'required|string|max:255',
        ];

        // Create a validator instance
        $validator = Validator::make($request->all(), $rules);

        // Check for validation failure
        if ($validator->fails()) {
            // Return a custom error response
            return response()->json([
                'responseCode' => 400,
                'responseMessage' => 'Validation errors occurred',
                'errors' => $validator->errors()
            ]);
        }

        DB::beginTransaction();

        try {

            $user = auth()->user();
            $staff = $user->getStaffProfile();

            $payment_code = $request['paymentCode'];
            $mat_no = $request['matNo'];
            $currencyCode = $request['currencyCode'];
            $amount = $request['amount'];
            $total_amount = $request['amount'];
            $receiptNo = $request['receiptNo'];

            $currency_rate = Utility::convertCurrencyByCode($currencyCode);

            $payment_type = PaymentType::where('payment_code', $payment_code)->first();

            $bank = $user->bank;

            if ($amount < 30){
                return response()->json([
                    'responseCode' => 400,
                    'responseMessage' => 'invalid amount'
                ]);
            }

            if (is_null($bank)) {
                return response()->json([
                    'responseCode' => 400,
                    'responseMessage' => 'invalid configuration'
                ]);
            }

            $check_payment = BankPayment::where('receipt_no',$receiptNo)->where('bank_id',$bank->id)->first();
            if (!is_null($check_payment)){
                return response()->json([
                    'responseCode' => 400,
                    'responseMessage' => 'Duplicate Payment'
                ]);
            }

            if (is_null($payment_type)) {
                return response()->json([
                    'responseCode' => 400,
                    'responseMessage' => 'invalid payment type'
                ]);
            }

            $student = Student::where('mat_no', $mat_no)->first();
            if (!is_null($student)) {

                if ($payment_type->can_deduct == "YES") {


                    $student_balance = !is_null($student->payment_balance) ? $student->payment_balance : 0.00;

                    $amount = $amount + $student_balance;

                    $payment_type_id = $payment_type->id;

                    $student_payment = new StudentPayment();
                    $student_payment->payment_type_id = $payment_type_id;
                    $student_payment->student_id = $student->id;
                    $student_payment->amount = $total_amount;
                    $student_payment->receipt_no = $receiptNo;
                    $student_payment->date = Carbon::today();
                    $student_payment->currency = $currencyCode;
                    $student_payment->rate = $currency_rate;
                    $student_payment->staff_id = $staff->id;
                    $student_payment->bank_id = $bank->id;
                    $student_payment->save();

                    // Get student enrolled semesters
                    $semesters = $student->getEnrollAndFutureSemesters()->filter(function ($s) use ($student) {
                        return $student->getRemainingBalance($s->id) > 0;
                    })->pluck('id')->toArray();

                    $semesters = collect($semesters)->sortBy('id');

                    foreach ($semesters as $semester_id) {
                        $semester = \App\Semester::find($semester_id);
                        if (is_null($semester)) {
                            continue;
                        }

                        //Get the remaining balance
                        $balance = $student->getRemainingBalance($semester_id);

                        if ($balance >= $amount) {

                            $payment = new \App\Payment;
                            $payment->student_payment_id = $student_payment->id;
                            $payment->semester_id = $semester_id;
                            $payment->student_id = $student->id;
                            $payment->staff_id = $staff->id;
                            $payment->amount = $amount;
                            $payment->currency = $currencyCode;
                            $payment->rate = $currency_rate;
                            $payment->payment_type_id = $payment_type_id;
                            $payment->receipt_no = $receiptNo;
                            $payment->system_receipt = Utility::generateReceipt();
                            $payment->date = Carbon::today();
                            $payment->bank_id = $bank->id;
                            $payment->save();

                            $bank_name = $payment->bank ? $payment->bank->name : 'N/A';

                            $message = "[ BANK CREATED PAYMENT VIA API FOR ] Student Name (" . $payment->student->getName() . ") , With Mat# (" . $payment->student->mat_no . "), 
                        With Major (" . $payment->student->getProgram() . "), in Semester (" . $payment->semester->getSemesterName() . "), With Payment Amount ("
                                . $payment->amount . "), Bank Name (" . $bank_name . ") Currency (" . $payment->currency . "), Rate (" . $payment->rate . "), Payment Type (" . $payment->payment_type->name . "), Receipt No ("
                                . $payment->receipt_no . "), Payment System Receipt (" . $payment->system_receipt . ") and Date (" . $payment->date . ")";
                            activity('Finance')->log($message);


                            $amount = 0.0;

                            break;

                        } else {

                            $payment = new \App\Payment;
                            $payment->student_payment_id = $student_payment->id;
                            $payment->semester_id = $semester_id;
                            $payment->student_id = $student->id;
                            $payment->staff_id = $staff->id;
                            $payment->amount = $balance;
                            $payment->currency = $currencyCode;
                            $payment->rate = $currency_rate;
                            $payment->payment_type_id = $payment_type_id;
                            $payment->receipt_no = $receiptNo;
                            $payment->system_receipt = Utility::generateReceipt();
                            $payment->date = Carbon::today();
                            $payment->bank_id = $bank->id;
                            $payment->save();

                            $bank_name = $payment->bank ? $payment->bank->name : 'N/A';

                            $message = "[ BANK CREATED PAYMENT VIA API FOR ] Student Name (" . $payment->student->getName() . ") , With Mat# (" . $payment->student->mat_no . "), 
                        With Major (" . $payment->student->getProgram() . "), in Semester (" . $payment->semester->getSemesterName() . "), With Payment Amount ("
                                . $payment->amount . "), Bank Name (" . $bank_name . ") Currency (" . $payment->currency . "), Rate (" . $payment->rate . "), Payment Type (" . $payment->payment_type->name . "), Receipt No ("
                                . $payment->receipt_no . "), Payment System Receipt (" . $payment->system_receipt . ") and Date (" . $payment->date . ")";
                            activity('Finance')->log($message);

                            // deduct balance from amount
                            $amount = $amount - $balance;
                        }
                    }

                    // update student payment balance
                    $student->payment_balance = $amount;
                    $student->save();

                    // Log Bank Payment Transaction
                    $bank_payment = new BankPayment();
                    $bank_payment->user_id = $user->id;
                    $bank_payment->payment_type_id = $payment_type_id;
                    $bank_payment->student_id = $student->id;
                    $bank_payment->amount = $total_amount;
                    $bank_payment->receipt_no = $receiptNo;
                    $bank_payment->date = Carbon::today();
                    $bank_payment->currency = $currencyCode;
                    $bank_payment->rate = $currency_rate;
                    $bank_payment->staff_id = $staff->id;
                    $bank_payment->bank_id = $bank->id;
                    $bank_payment->save();

                    DB::commit();

                    return response()->json([
                        'responseCode' => 200,
                        'responseMessage' => 'success'
                    ]);

                } else {

                    $semester = Utility::getCurrentSemester();
                    if (is_null($semester)) {
                        return response()->json([
                            'responseCode' => 400,
                            'responseMessage' => 'no semester found'
                        ]);
                    } else {

                        // Log Bank Payment Transaction
                        $bank_payment = new BankPayment();
                        $bank_payment->user_id = $user->id;
                        $bank_payment->payment_type_id = $payment_type->id;
                        $bank_payment->student_id = $student->id;
                        $bank_payment->amount = $total_amount;
                        $bank_payment->receipt_no = $receiptNo;
                        $bank_payment->date = Carbon::today();
                        $bank_payment->currency = $currencyCode;
                        $bank_payment->rate = $currency_rate;
                        $bank_payment->staff_id = $staff->id;
                        $bank_payment->bank_id = $bank->id;
                        $bank_payment->save();

                        $student_payment = new StudentPayment();
                        $student_payment->payment_type_id = $payment_type->id;
                        $student_payment->student_id = $student->id;
                        $student_payment->amount = $total_amount;
                        $student_payment->receipt_no = $receiptNo;
                        $student_payment->date = Carbon::today();
                        $student_payment->currency = $currencyCode;
                        $student_payment->rate = $currency_rate;
                        $student_payment->staff_id = $staff->id;
                        $student_payment->bank_id = $bank->id;
                        $student_payment->save();

                        $payment = new \App\Payment;
                        $payment->student_payment_id = $student_payment->id;
                        $payment->semester_id = $semester->id;
                        $payment->student_id = $student->id;
                        $payment->staff_id = $staff->id;
                        $payment->amount = $total_amount;
                        $payment->currency = $currencyCode;
                        $payment->rate = $currency_rate;
                        $payment->payment_type_id = $payment_type->id;
                        $payment->receipt_no = $receiptNo;
                        $payment->system_receipt = Utility::generateReceipt();
                        $payment->date = Carbon::today();
                        $payment->bank_id = $bank->id;
                        $payment->save();

                        $bank_name = $payment->bank ? $payment->bank->name : 'N/A';

                        $message = "[ BANK CREATED PAYMENT VIA API FOR ] Student Name (" . $payment->student->getName() . ") , With Mat# (" . $payment->student->mat_no . "), 
                        With Major (" . $payment->student->getProgram() . "), in Semester (" . $payment->semester->getSemesterName() . "), With Payment Amount ("
                            . $payment->amount . "), Bank Name (" . $bank_name . ") Currency (" . $payment->currency . "), Rate (" . $payment->rate . "), Payment Type (" . $payment->payment_type->name . "), Receipt No ("
                            . $payment->receipt_no . "), Payment System Receipt (" . $payment->system_receipt . ") and Date (" . $payment->date . ")";
                        activity('Finance')->log($message);

                        DB::commit();

                        return response()->json([
                            'responseCode' => 200,
                            'responseMessage' => 'success'
                        ]);
                    }

                }

            } else {
                return response()->json([
                    'responseCode' => 400,
                    'responseMessage' => 'no student record'
                ]);
            }


        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json([
                'responseCode' => 400,
                'responseMessage' => 'error '
            ]);
        }
    }
}
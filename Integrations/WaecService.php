<?php

namespace App\Integrations;

use App\WaecResultRequest;
use GuzzleHttp\Client;
//use function App\env;

class WaecService
{

    public function getHeaders()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
            'Connection' => 'Keep-Alive',
            'Keep-Alive' => '***',
            'SecurityToken' => env('WAEC_SECRET'),
        ];
        return new Client([
            'headers' => $headers,
            'timeout'         => 20,
            'connect_timeout' => 1.5
        ]);
    }

    public static function getExams()
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'accept' => 'application/json',
                'Connection' => 'Keep-Alive',
                'Keep-Alive' => '***',
            ];
            $client = new Client([
                'headers' => $headers,
                'timeout'         => 20,
                'connect_timeout' => 1.5
            ]);

            $EXAM_URL = "http://unifiedresultapi.vatebra.com/gambiaApi/fetchexams";
            $response = $client->request('GET', $EXAM_URL);
            if ($response->getStatusCode() == 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function fetchResult($exam_type, $exam_no, $exam_year)
    {
        try {

            $waec_result = WaecResultRequest::where([
                ['exam_year', $exam_year],
                ['exam_no', $exam_no],
            ])->first();


            if (!is_null($waec_result)) {
                $waec_grades = $waec_result->result_grades;
                $results = [];
                foreach ($waec_grades as $grade) {
                    $result = [
                        "SerialNo" => $grade->serial_no,
                        "Subject" => $grade->subject,
                        "Grade" => $grade->grade,
                    ];
                    array_push($results, $result);
                }
                $response_body = [
                    "ExamNo" => $waec_result->exam_no,
                    "ExamYear" => $waec_result->exam_year,
                    "ExamCenter" => $waec_result->exam_center,
                    "FullName" => $waec_result->full_name,
                    "Gender" => $waec_result->gender,
                    "DOB" => $waec_result->dob,
                    "Examination" => $waec_result->examination,
                    "PassportUrl" => $waec_result->passport_url,
                    "Remark" => $waec_result->remark,
                    "Status" => $waec_result->status,
                    "Grades" => $results
                ];

                return $response_body;

            } else {


                $headers = [
                    'Content-Type' => 'application/json',
                    'accept' => 'application/json',
                    'Connection' => 'Keep-Alive',
                    'Keep-Alive' => '***',
                    'SecurityToken' => env('WAEC_SECRET'),
                ];

                $client = new Client([
                    'headers' => $headers
                ]);

                $body = [
                    "ExamType" => $exam_type,
                    "ExamNo" => $exam_no,
                    "ExamYear" => $exam_year
                ];

                $RESULT_URL = "http://unifiedresultapi.vatebra.com/gambiaApi/fetchresult";

                $response = $client->request("POST", $RESULT_URL, [
                    'form_params' => $body
                ]);

                if ($response->getStatusCode() == 200) {
                    return json_decode($response->getBody(), true);
                }

            }

        } catch (\Exception $e) {
            return null;
        }

    }
}

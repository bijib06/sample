<?php
namespace App\Integrations;

use GuzzleHttp\Client;
class IgcseService {
    public function connect()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
            'Connection' => 'Keep-Alive',
            'Keep-Alive' => '***',
        ];
        return new Client([
            'headers' => $headers,
        ]);
    }

    public  function verifyResult($body){
        try {
            $client = connect();
            $URL = "";
            $response = $client->request(
                "POST",
                $URL,
                ['json' => $body]
            );
            $code = $response->getStatusCode();
            return [$code,json_decode($response->getBody()->getContents(),true)];
        }catch (\Exception $exp){
            return null;
        }
    }

    function getArrayValueByKey($array, $key)
    {
        $keys = explode(' ', $key);

        foreach ($keys as $keyPart) {
            if (isset($array[$keyPart])) {
                $array = $array[$keyPart];
            } else {
                return null;
            }
        }

        return $array;
    }
    public static function processApplicantResult($body,$applicant_id){
        try {
             $response = verifyResult($body);
             if (!is_null($response) && $response["status_code"] == 200){

                 $results = $response['result'];

                 $student = $response['student'];

                 $igcse_request = new IgcseRequest();
                 $igcse_request->application_id = $applicant_id;
                 $igcse_request->candidate_name = $student[0];
                 $igcse_request->candiate_number = $student[1];
                 $igcse_request->dob = $student[2];
                 $igcse_request->gender = $student[3];
                 $igcse_request->center = $student[4];
                 $igcse_request->country = $student[5];
                 $igcse_request->series = $student[6];
                 $igcse_request->is_verified = "YES";
                 $igcse_request->save();

                 foreach ($results as $result){
                     $igcse_result = new IgcseSubject();
                     $igcse_result->igcse_result_request_id = $igcse_request->id;
                     $igcse_result->series = $result['Series'];
                     $igcse_result->qualification = $result['Qualification'];
                     $igcse_result->subject = $result['Subject'];
                     $igcse_result->syllabus = $result['Syllabus/Option'];
                     $igcse_result->result = $result['Result'];
                     $igcse_result->save();
                 }
                return "Result Successfully Verified";
             }
            return "Result Successfully Verified";

        }catch (\Exception $exp){
            return null;
        }
    }
}

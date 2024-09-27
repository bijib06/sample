<?php


namespace App\Integrations;

use GuzzleHttp\Client;
//use function App\env;

class IntegrationAPI
{
//    protected $client;

    public static function connect()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
            'Connection' => 'Keep-Alive',
            'Keep-Alive' => '***',
            'Authorization' => 'Bearer '.env('INT_SECRET'),
        ];
        return new Client([
            'headers' => $headers,
        ]);
    }

    public static function endPointPostRequest($url,$body){
        try{
            $client = IntegrationAPI::connect();
            $response = $client->request("POST",'https://utgerpgsuite.herokuapp.com/'.$url,['json' => $body]);
            $code = $response->getStatusCode();
            return [$code,json_decode($response->getBody()->getContents(),true)];
        }catch (\Exception $e){
            return ["404",["message" => $e->getMessage(). " Please Try Again ", "code" => 0]];
        }
    }

    public function response_handler($response){
        if ($response){
            return json_decode($response->getBody()->getContents(),true);
        }
        return [];
    }
}
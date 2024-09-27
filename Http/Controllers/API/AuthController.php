<?php

namespace App\Http\Controllers\API;
//
//use JWTAuth;
//use App\User;
//use Validator;
//use Illuminate\Http\Request;
//use App\Http\Requests\RegisterAuthRequest;
//use Tymon\JWTAuth\Exceptions\JWTException;
//use Illuminate\Support\Facades\Auth;
//use App\Http\Controllers\Controller;
//
//class AuthController extends Controller
//{
//
//	// public function __construct(){
//	// 	$this->middleware('auth:api',['except' => ['login']]);
//	// }
//
//	/**
//     * Get a JWT via given credentials.
//     *
//     * @return \Illuminate\Http\JsonResponse
//     */
//    public function login(Request $request)
//    {
//
//    	$validator = Validator::make($request->all(), [
//            'email' => 'required|email',
//            'password' => 'required|string|min:6',
//        ]);
//
//        if ($validator->fails()) {
//            return response()->json($validator->errors(), 422);
//        }
//
//        $token = null;
//
//        $credentials = $request->only('email', 'password');
//
//        if (! $token = JWTAuth::attempt($credentials)) {
//            return response()->json(['error' => 'Unauthorized'], 401);
//        }
//
//        return response()->json([
//            'success' => true,
//            'token' => $token,
//        ]);
//
//    }
//
//
//}

use App\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validation rules
        $rules = [
            'email' => 'required|string|max:255',
            'password' => 'required|string',
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
            $credentials = request(['email', 'password']);

            if (! $token = auth('api')->attempt($credentials)) {
                return response()->json([
                    'responseCode' => 400,
                    'responseMessage' => "Unauthorized",
                ]);
            }

            $user =  Account::where('email',$request['email'])->first();

            if (($user->api_enabled != "YES") && ($user->bank_api_enabled != "YES")){
                return response()->json([
                    'responseCode' => 400,
                    'responseMessage' => "Unauthorized",
                ]);
            }

            return $this->respondWithToken($token);
        }catch (\Exception $exception){
            return response()->json([
                'responseCode' => 400,
                'responseMessage' => "Error "
            ]);
        }

    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = auth()->user();
        return response()->json($user);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            auth()->logout();
            return response()->json([
                'responseCode' => 400,
                'responseMessage' => "LoggedOut",
            ]);
        }catch (\Exception $exp){
            return response()->json([
                'responseCode' => 400,
                'responseMessage' => "Error"
            ]);

        }


    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            "responseCode" => 200,
            'accessToken' => $token,
            'tokenType' => 'bearer',
            'expiresIn' => auth('api')->factory()->getTTL() * 60
        ]);
    }
}
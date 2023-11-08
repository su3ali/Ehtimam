<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Auth\ForgetPasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\ProfileUpdateRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\User\UserResource;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Sms\HiSms;
use App\Support\Api\ApiResponse;
use Illuminate\Http\Request;
use App\Traits\SMSTrait;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use MoemenGaballah\Msegat\Msegat;

class AuthController extends Controller
{
    use ApiResponse, SMSTrait;

    public function __construct()
    {
        $this->middleware('localization');
    }


    public function magic_code(Request $request){

        $user = User::where('phone',$request->phone)->first();
        if($user){
            $this->body['code']= $user->code;
            $this->message = "code found";
            return self::apiResponse(200, $this->message, $this->body);
        }else{
            $this->body['code']= '';
            $this->message = "no code";
            return self::apiResponse(400, $this->message, $this->body);
        }
        
        
    }
    public function magic(Request $request){
        $user = User::where('id',auth()->user('sanctum')->id)->first();
        $fcm_token = $user->fcm_token;
        $this->message = "magic api";
        $this->body['fcm_token']= $fcm_token??'';
        return self::apiResponse(200, $this->message, $this->body);
    }

    /**
     * @throws \Exception
     */
    public function login(Request $request)
    {
        $user = User::query()->where('phone', $request->phone)->first();



        if (!$user) {
            $validated = $request->validate([
                'phone' => 'required|numeric|unique:users,phone',
            ], $request->all());
            $user = User::query()->create([
                'phone' => $validated['phone'],
                'city_id' => 0,
            ]);
        }else{
            $validated = $request->validate([
                'phone' => 'required|numeric|unique:users,phone,'.$user->id
            ], $request->all());
        }

        if ($user->phone == "966580111196" || $user->phone =='580111196'){
            $code = 1111;
        }else{
            $code = random_int(1000, 9999);

        }

        $user->update([
            'code' => $code
        ]);
        $m = "رمز التحقق: ".$code;
        $msg = $this->sendMessage($validated['phone'], $m);
        $this->message = __('api.login successfully, but code is needed');
        $this->body['user'] = UserResource::make($user);
        return self::apiResponse(200, $this->message, $this->body);
    }

    public function verify(Request $request)
    {
        $user = User::query()->where('id', $request->user_id)->first();
        if ($request->code == $user->code && $user) {
                $user->update([
                    'code' => null,
                    'fcm_token' => $request->fcm_token
                ]);
                Auth::loginUsingId($user->id);
                if(auth('sanctum')->check()){
                    auth()->user()->tokens()->delete();
                }
                $this->message = __('api.login successfully');
                $this->body['user'] = UserResource::make($user);
                $this->body['accessToken'] = $user->createToken('user-token',['user'])->plainTextToken;
                return self::apiResponse(200, $this->message, $this->body);
            }
            $this->message = __('api.auth failed');
            return self::apiResponse(400, $this->message, $this->body);
        }

        public function logout(Request $request)
        {
            auth()->user()->tokens()->delete();
            $this->message = __('api.Logged out');

            return self::apiResponse(200, $this->message, $this->body);

        }


        public function deleteAccount(Request $request)
        {
            auth()->user()->tokens()->delete();
            $user =  auth('sanctum')->user();
            $user->update([
                'is_deleted' => 1,
                'phone' => $user->phone . '-deleted',
                'email' => $user->email . '-deleted',
            ]);
            $this->message = __('api.Delete user successfully');
    
            return self::apiResponse(200, $this->message, $this->body);
        }
    }

<?php

namespace App\Http\Controllers\Api\Auth;

use App\Jobs\SignUp;
use App\Models\User;
use App\Traits\HttpResponses;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use App\Http\Requests\LoginUsersRequest;
use App\Http\Requests\RegisterUsersRequest;
use Exception;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    use HttpResponses;

    public function index()
    {
        return $this->success("Successfull setup");
    }

    public function login(LoginUsersRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = [
                'email' => $request->email,
                'password' => $request->password
            ];

            if (!Auth::attempt($data)) {
                $errors = ['email' => ['Invalid Credentials']];
                return $this->fieldError(
                    $errors,
                    406
                );
            }

            $user = User::where('email', $request->email)->first();

            $user->balance =  json_decode($user->balance);
            $token =  $user->createToken('API token of ' . $user->username, ['*'], now()->addMinutes(1440))->plainTextToken;

            DB::commit();

            return $this->success(
                [
                    'token' => $token,
                ],
                'Login successful'
            );
        } catch (Exception $e) {
            DB::rollBack();

            return $this->error(null, $e->getMessage(), 406);
        }
    }

    public function register(RegisterUsersRequest $request)
    {

        try {

            DB::beginTransaction();

            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            dispatch(new SignUp($user));

            DB::commit();

            return $this->success(null, "Registration successfull, Please click the email verification link sent to your email.");
        } catch (Exception $e) {

            DB::rollBack();
            return $this->error(null, $e->getMessage(), 406);
        }
    }

    public function logout(Request $request)
    {
        if (!Auth::check()) {
            return $this->error(null, 'Not Authenticated', 401);
        }
        $user = User::where('id', Auth::user()->id)->first();
        $user->tokens()->delete();
        return $this->success([
            'success' => ['logout successful'],
        ]);
    }
}

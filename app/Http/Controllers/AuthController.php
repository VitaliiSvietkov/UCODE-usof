<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

use App\Mail\UsofMail;
use Illuminate\Support\Facades\Mail;

use App\Models\User;
use Validator;
use DB;


class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct() {
        //$this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request) {
        if (auth()->user())
            $this->logout();

        if (!$token = auth()->attempt($request->all())) {
            $message = [
                'status' => "FAIL", 
                'message' => 'The login or password is incorrect'
            ];
            return response()->json($message, 401);
        }

        $response = $this->createNewToken($token);
        $user = User::where('login', $request['login'])->first();
        $user->remember_token = explode('.', $token)[2];
        $user->save();
        return $response;
    }

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request) {
        $credentials = $request->only([
            'login', 
            'password', 
            'email', 
            'full_name', 
            'profile_picture'
        ]);
        $validator = Validator::make($credentials, [
            'login' => 'required|unique:App\Models\User,login',
            'password' => 'required|min:6|max:14',
            'email' => 'required|unique:App\Models\User,email',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => ($validator->errors())->first()
            ]);
        }
        $credentials['password'] = Hash::make($credentials['password']);
        
        $image_data;
        if (isset($credentials['profile_picture'])) {
            $image_data = $credentials['profile_picture'];
            unset($credentials['profile_picture']);
        }

        $user = User::create($credentials);

        if (isset($image_data)) {
            $image = $image_data;
            $image_data = 'avatars/' . $user->id . '.png';
            $file = fopen($image_data, "w");
            fwrite($file, base64_decode($image));
            fclose($file);

            $user->profile_picture = $image_data;
            $user->save();
        }

        return response()->json([
            'status' => 'OK',
            'message' => 'User successfully registered',
        ]);
    }


    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout() {
        //Auth::guard('api')->logout();

        $user = User::where('remember_token', explode('.', explode(' ', request()->header('Authorization'))[1])[2])->first();
        //$user = User::where('remember_token', explode(' ', request()->header('Authorization'))[1])->first();
        $user->remember_token = null;
        $user->save();

        return response()->json([
            'status' => 'OK', 
            'message' => 'User successfully signed out'
        ]);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh() {
        return $this->createNewToken(auth()->refresh());
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userProfile() {
        return response()->json(auth()->user());
    }

    /**
     * Send a reset link to user email
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function passwordReset(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Email field is missing or invalid'
            ]);
        }

        // Get a user with request email
        $email = $request->input('email');
        $user = DB::select('SELECT * FROM users WHERE email=:email', ['email' => $email]);
        if (empty($user)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'There is no user with such email address'
            ]);
        }
        
        // Create an object for the sending email and send it
        $mailObj = new \stdClass();
        $token = Str::random(12); // remember_token
        $mailObj->path = "http://localhost:{$_SERVER['SERVER_PORT']}/api/auth/password-reset/$token"; // link for the email
        $mailObj->receiver = $user[0]->full_name;
        Mail::to($email)->send(new UsofMail($mailObj));

        // Remember the token
        DB::update('UPDATE users SET remember_token=:token WHERE email=:email', ['token' => $token, 'email' => $email]);
        
        return response()->json([
            'status' => 'OK',
            'message' => 'A password reset link was send to your email address'
        ]);
    }

    /**
     * Change a password
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function passwordChange(Request $request, $confirm_token) {
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:6|max:14'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'Password length should be not less than 6 and not bigger than 14'
            ]);
        }

        // Get the user and return if the token is incorrect
        $user = DB::select('SELECT * FROM users WHERE remember_token=:token', ['token' => $confirm_token]);
        if (empty($user)) {
            return response()->json([
                'status' => 'FAIL',
                'message' => 'This link has already expired or incorrect'
            ], 200);
        }

        // Change password
        $new_password = Hash::make($request->input('password'));
        DB::update('UPDATE users SET password=:pass WHERE remember_token=:token', ['pass' => $new_password, 'token' => $confirm_token]);

        // Clear the remember_token field
        DB::update('UPDATE users SET remember_token=NULL WHERE remember_token=:token', ['token' => $confirm_token]);

        return response()->json([
            'status' => 'OK',
            'message' => 'A password has been changed'
        ], 200);
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token){
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
        ]);
    }

}
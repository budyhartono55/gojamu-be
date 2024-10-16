<?php

namespace App\Repositories\Auth;

use App\Repositories\Auth\AuthInterface;
use App\Traits\API_response;
use App\Models\User;
use App\Notifications\ResetPasswordVerificationNotification;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Otp;
use App\Helpers\Services\EmailService;
use App\Models\Contact;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use App\Mail\resetPassword;



class AuthRepository implements AuthInterface
{
    private $User;
    private $otp;
    use API_response;

    public function __construct(User $User)
    {
        $this->User = $User;
        $this->otp = new Otp;
    }

    public function register($request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required',
            'username'     => 'required|unique:users',
            'email'     => 'required|email',
            'password'           => 'required',
            'confirm_password' => 'required|same:password'
        ]);

        if ($validator->fails()) {
            return $this->error("Validator!", $validator->errors(), 422);
        }

        try {

            $input = $request->all();
            $input['password'] = bcrypt($input['password']);
            $user = User::create($input);

            // $success['token'] = $user->createToken('auth_token')->plainTextToken;
            $success['name'] = $user->name;

            return $this->success("Register Berhasil!", $success);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e);
        }
    }

    public function login($request)
    {
        $validator = Validator::make($request->all(), [
            'username'     => 'required',
            'password'           => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error("Validator!", $validator->errors(), 422);
        }
        try {

            if (Auth::attempt(['username' => $request->username, 'password' => $request->password]) or Auth::attempt(['email' => $request->username, 'password' => $request->password])) {
                $auth = Auth::user();
                $success['token'] = $auth->createToken('auth_token')->plainTextToken;
                $success['username'] = $auth->username;
                $success['name'] = $auth->name;

                return $this->success("Login Sukses", $success);
            } else {

                return $this->error("Login Gagal", "Username atau Password Salah", 400);
            }
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e);
        }
    }

    public function logout($request)
    {
        try {
            // auth()->user()->currentAccessToken()->delete();
            $request->user('sanctum')->tokens()->delete();
            return $this->success("Logout Berhasil!", "Logout Berhasil");
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e);
        }
    }

    //changePassword
    public function changePassword($request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'new_password' => 'required',
            'confirm_newPassword' => 'required|same:new_password'
        ]);

        if ($validator->fails()) {
            return $this->error("Validator!", $validator->errors(), 422);
        }

        try {
            if (!Hash::check($request->old_password, auth()->user()->password)) {
                return $this->error("Validator!", "The old password does not match", 400);
            }

            $update =  User::whereId(auth()->user()->id)->update([
                'password' => Hash::make($request->new_password)
            ]);
            if ($update) {
                return $this->success("Sukses!", "Password updated successfully");
            };
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e, 500);
        }
    }

    public function forgotPassword($request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->error("Bad Request", $validator->errors(), 422);
        }
        try {
            $input = $request->only('email');
            $user = User::where('email', $input)->first();
            if (!$user) {
                return $this->error("Not Found", "Your email doesn't exist in our database", 404);
            }
            $name = $user->name;
            $twitterContact = Contact::pluck('twitter')->implode(', ');
            $facebookContact = Contact::pluck('facebook')->implode(', ');
            $instagramContact = Contact::pluck('instagram')->implode(', ');
            $emailContact = Contact::pluck('email')->implode(', ');
            $contact = Contact::pluck('contact')->implode(', ');
            $address = Contact::pluck('address')->implode(', ');
            $website = Contact::pluck('website')->implode(', ');
            $nama_dinas = Setting::pluck('name_dinas')->implode(', ');
            $password_reset = env("PASSWORD_RESET");

            $otp = $this->otp->generate($request->email, 6, 15);
            $toView = [
                'otp' => $otp->token,
                'name' => $name,
                'email' => $emailContact,
                'facebook' => $facebookContact,
                'twitter' => $twitterContact,
                'instagram' => $instagramContact,
                'contact' => $contact,
                'address' => $address,
                'website' => $website,
                'nama_dinas' => $nama_dinas,
                'password_reset' => $password_reset,
            ];
            $view = view('emails.forgetPassword')->with('dataView', $toView);
            $renderHtml = $view->render();
            if ($renderHtml) {
                $email = $request->email;
                $receiver = $name;
                $subject = 'Lupa kata sandi?';
                // EmailService::sendEmail($email, $renderHtml, $receiver, $subject);

                //return $this->success("Success", "Email telah terkirim ke ($request->email), mohon cek inbox atau spam anda!");
                return $this->error("Bad Request", "Mohon Maaf, fitur ini masih dalam maintanance.", 400);
            }
            // Mail::to($request->email)->send(new resetPassword($dataView));
            // $user->notify(new ResetPasswordVerificationNotification());
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function resetPassword($request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|max:6',
            'new_password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error("Bad Request", $validator->errors(), 422);
        }
        try {
            $input = $request->only('email');
            $user = User::where('email', $input)->first();

            if (!$user) {
                return $this->error("Not Found", "Your email doesn't exist in our database", 404);
            }

            $otp2 = $this->otp->validate($request->email, $request->otp);
            if (!$otp2->status) {
                return $this->error("Kode OTP yang anda masukkan tidak dikenal, moho cek Email anda!", $otp2, 401);
            }
            $user = User::where('email', $request->email)->first();
            $update =  $user->update([
                'password' => Hash::make($request->new_password)
            ]);
            $user->tokens()->delete();
            if ($update) {
                return $this->success("Success", "Reset password successfully");
            };
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function cekLogin()
    {
        $check = Auth::check();
        if ($check) {
            return $this->success("Success", "Token Valid", 200);
        }
        return $this->error("Failed", "Token not Valid", 401);
    }
}

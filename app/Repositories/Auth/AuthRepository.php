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
use Carbon\Carbon;
use App\Helpers\Helper;


class AuthRepository implements AuthInterface
{
    private $User;
    private $otp;
    private $keyRedis = "user-";
    use API_response;

    public function __construct(User $User)
    {
        $this->User = $User;
        $this->otp = new Otp;
    }

    public function register($request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|email|max:255|unique:users',
            'password' => [
                'required',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*[!@#$%^&*(),.?":{}|<>]).+$/'
            ],
            'confirm_password' => 'required|same:password'
        ]);

        // Jika validasi gagal
        if ($validator->fails()) {
            return $this->error("Validation Error", $validator->errors(), 422);
        }

        try {
            $data = [
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'jenis_kelamin' => $request->jenis_kelamin,
                'tentang' => $request->tentang,
                'password' => bcrypt($request->password),
                'address' => $request->address,
                'contact' => $request->contact

            ];
            // Buat pengguna baru
            $user = User::create($data);
            Helper::deleteRedis($this->keyRedis . "*");

            // Jika token dibutuhkan, Anda bisa menambahkan ini (komentar jika tidak digunakan)
            // $token = $user->createToken('auth_token')->plainTextToken;

            // Persiapkan respons sukses
            $success = [
                'name' => $user->name,
                // 'token' => $token // Uncomment untuk token
            ];

            return $this->success("Registrasi Berhasil!", $success);
        } catch (\Throwable $e) { // Menangani semua jenis error (Exception atau Error)
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }


    public function login($request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Jika validasi gagal
        if ($validator->fails()) {
            return $this->error("Validator failed!", $validator->errors(), 422);
        }

        try {
            // Attempt login with either username or email
            if (
                Auth::attempt(['username' => $request->username, 'password' => $request->password]) ||
                Auth::attempt(['email' => $request->username, 'password' => $request->password])
            ) {

                // Get the authenticated user
                $auth = Auth::user();
                // Check if the user is active, if not return an error
                if ($auth->active != 1) {
                    return $this->error("Akun Anda tidak aktif", "Akun Anda tidak aktif. Silakan hubungi Administrator.", 403);
                }
                // Update last login time
                $auth->last_login = Carbon::now();
                $auth->save();

                // Prepare success response
                $success = [
                    'token' => $auth->createToken('auth_token')->plainTextToken,
                    'username' => $auth->username,
                    'name' => $auth->name,
                ];

                return $this->success("Login Successful", $success);
            } else {
                return $this->error("Login Failed", "Incorrect username or password", 400);
            }
        } catch (\Throwable $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }


    public function logout($request)
    {
        try {
            // Hapus semua token pengguna yang sedang aktif
            $request->user('sanctum')->tokens()->delete();
            return $this->success("Logout Berhasil!", "Anda telah logout.");
        } catch (\Throwable $e) {
            return $this->error("Internal Server Error!", $e->getMessage(), 500);
        }

        // try {
        //     // auth()->user()->currentAccessToken()->delete();
        //     $request->user('sanctum')->tokens()->delete();
        //     return $this->success("Logout Berhasil!", "Logout Berhasil");
        // } catch (\Exception $e) {
        //     // return $this->error($e->getMessage(), $e->getCode());
        //     return $this->error("Internal Server Error!", $e);
        // }
    }

    public function changePassword($request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'new_password' => [
                'required',
                'min:8', // Minimum length of 8 characters
                'regex:/^(?=.*[A-Z])(?=.*[!@#$%^&*(),.?":{}|<>]).+$/', // At least 1 uppercase and 1 symbol
            ],
            'confirm_newPassword' => 'required|same:new_password'
        ]);

        if ($validator->fails()) {
            return $this->error("Validation Error!", $validator->errors(), 422);
        }

        try {
            // Check if the old password matches the stored password
            if (!Hash::check($request->old_password, auth()->user()->password)) {
                return $this->error("Validation Error!", "The old password does not match", 400);
            }

            // Update the password
            $update = User::whereId(auth()->user()->id)->update([
                'password' => Hash::make($request->new_password)
            ]);

            if ($update) {
                return $this->success("Success!", "Password updated successfully");
            }

            return $this->error("Internal Server Error", "Failed to update password", 500);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage(), 500);
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

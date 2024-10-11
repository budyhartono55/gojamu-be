<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Auth\AuthInterface;


class AuthController extends Controller
{

    private $authRepository;

    public function __construct(AuthInterface $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    //M E T H O D E ======================
    // register
    public function register(Request $request)
    {
        return $this->authRepository->register($request);
    }

    //login
    public function login(Request $request)
    {
        return $this->authRepository->login($request);
    }

    //logout
    public function logout(Request $request)
    {
        return $this->authRepository->logout($request);
    }

    //changePassword
    public function change_password(Request $request)
    {
        return $this->authRepository->changePassword($request);
    }
    //forgotPassword
    public function forgot_password(Request $request)
    {
        return $this->authRepository->forgotPassword($request);
    }
    //reset password
    public function reset_password(Request $request)
    {
        return $this->authRepository->resetPassword($request);
    }
    public function cekLogin()
    {
        return $this->authRepository->cekLogin();
    }
}

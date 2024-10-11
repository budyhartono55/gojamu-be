<?php

namespace App\Repositories\Auth;

interface AuthInterface
{
    public function register($request);
    public function login($request);
    public function logout($request);
    public function changePassword($request);
    public function forgotPassword($request);
    public function resetPassword($request);
    public function cekLogin();
}

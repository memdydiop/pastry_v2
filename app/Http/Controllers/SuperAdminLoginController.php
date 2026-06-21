<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SuperAdminLoginController extends Controller
{
    public function create()
    {
        return view('pages.super-admin-login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ]);
        }

        if (!$user->is_super_admin) {
            return back()->withErrors([
                'email' => __('You do not have super-admin access.'),
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        return redirect()->intended('/admin');
    }
}

<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminLoginController extends Controller
{
    public function index()
    {
        return view('admin.login');
    }

    // This method will authenticate the user and redirect to the dashboard page
    public function authenticate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->passes()) {
            if (Auth::guard('admin')->attempt(['email' => $request->email, 'password' => $request->password], $request->get('remember'))) {
                //
                $admin = Auth::guard('admin')->user();

                if ($admin->role == 0) {

                    return redirect()->route('admin.dashboard');
                } else {
                    Auth::guard('admin')->logout();
                    session()->flash('error', 'You are not authorized to access admin panel.');
                    return redirect()->route('admin.login')->with('error', 'You are not authorized to access admin panel.');
                }
            } else {
                session()->flash('error', 'Either Email/Pasword is incorrect.');
                return redirect()->route('admin.login')->with('error', 'Either Email/Pasword is incorrect.');
            }
        } else {
            return redirect()
                ->route('admin.login')
                ->withErrors($validator)
                ->withInput($request->only('email'));
        }
    }
}

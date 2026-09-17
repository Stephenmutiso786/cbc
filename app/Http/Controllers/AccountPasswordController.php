<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountPasswordController extends Controller
{
    public function edit()
    {
        return view('account.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']]);
        $request->user()->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();

        return redirect()->route('student.dashboard')->with('success', 'Password changed successfully.');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function maintenanceLogin(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        // Redirect based on role
        $user = Auth::user();

        if ($user->hasRole(['admin', 'super-admin', 'headteacher', 'principal', 'deputy-headteacher', 'deputy'])) {
            return redirect()->route('admin.dashboard');
        } elseif ($user->hasRole(['hod', 'teacher', 'class-teacher', 'pre-primary-teacher', 'lower-primary-teacher', 'upper-primary-teacher', 'junior-secondary-teacher'])) {
            return redirect()->route('teacher.dashboard');
        } elseif ($user->hasRole('parent')) {
            return redirect()->route('parent.dashboard');
        } elseif ($user->hasRole(['bursar', 'accountant'])) {
            return redirect()->route('finance.dashboard');
        }

        // Custom roles created in Role Management must still land in the
        // correct portal based on their assigned capabilities.
        if ($user->canAny(['manage system settings', 'manage roles', 'manage staff', 'manage curriculum', 'manage fees', 'view finance reports'])) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->canAny(['enter marks', 'view assessments', 'view results', 'view notes', 'view timetable'])) {
            return redirect()->route('teacher.dashboard');
        }
        if ($user->canAny(['view fees', 'record payments', 'view finance reports'])) {
            return redirect()->route('finance.dashboard');
        }

        return redirect()->intended('/');
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}

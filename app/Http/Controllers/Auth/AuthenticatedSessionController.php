<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Learner;
use App\Models\User;
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
            'login'    => ['required', 'string'],
            'password' => ['required'],
        ]);

        $email = $credentials['login'];
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $learners = Learner::withoutSchoolScope()->where('admission_number', trim($credentials['login']))->whereNotNull('user_id')->with('user')->limit(2)->get();
            if ($learners->count() === 1 && $learners->first()->user) {
                $email = $learners->first()->user->email;
            } else {
                throw ValidationException::withMessages(['login' => 'Use the learner admission number or your account email address.']);
            }
        }

        $account = User::withoutSchoolScope()->where('email', $email)->first();
        if ($account && in_array($account->status, ['suspended', 'inactive'], true)) {
            throw ValidationException::withMessages([
                'login' => $account->status === 'suspended'
                    ? 'This account has been suspended. Please contact your school administrator.'
                    : 'This account is inactive. Please contact your school administrator.',
            ]);
        }

        if (!Auth::attempt(['email' => $email, 'password' => $credentials['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'login' => 'The provided credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        // Redirect based on role
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        if ($user->hasRole('super-admin')) {
            return redirect()->route('admin.platform-dashboard.index');
        } elseif ($user->hasRole('it-team')) {
            return redirect()->route('it.support.index');
        } elseif ($user->hasRole(['school-admin', 'headteacher', 'principal', 'deputy-headteacher', 'deputy'])) {
            return redirect()->route('admin.dashboard');
        } elseif ($user->hasRole(['hod', 'teacher', 'class-teacher', 'pre-primary-teacher', 'lower-primary-teacher', 'upper-primary-teacher', 'junior-secondary-teacher'])) {
            return redirect()->route('teacher.dashboard');
        } elseif ($user->hasRole('parent')) {
            return redirect()->route('parent.dashboard');
        } elseif ($user->hasRole(['bursar', 'accountant'])) {
            return redirect()->route('finance.dashboard');
        } elseif ($user->hasRole('learner')) {
            return redirect()->route('student.dashboard');
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

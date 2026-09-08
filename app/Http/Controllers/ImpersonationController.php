<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ImpersonationController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);
        return view('admin.impersonate', ['users' => User::where('id', '<>', auth()->id())->with('roles')->orderBy('name')->get()]);
    }

    public function start(Request $request, User $user)
    {
        abort_unless(auth()->user()->hasRole('super-admin'), 403);
        abort_if($user->is(auth()->user()) || $user->hasRole('super-admin'), 422, 'The Super Admin account cannot impersonate another Super Admin.');

        $originalId = auth()->id();
        $auditId = DB::table('impersonation_audits')->insertGetId([
            'impersonator_id' => $originalId,
            'target_id' => $user->id,
            'ip_address' => $request->ip(),
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $request->session()->put(['impersonator_id' => $originalId, 'impersonation_audit_id' => $auditId]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function stop(Request $request)
    {
        $originalId = $request->session()->get('impersonator_id');
        abort_unless($originalId && auth()->id() !== $originalId, 403);
        $auditId = $request->session()->get('impersonation_audit_id');
        if ($auditId) DB::table('impersonation_audits')->whereKey($auditId)->update(['ended_at' => now(), 'updated_at' => now()]);
        Auth::loginUsingId($originalId);
        $request->session()->forget(['impersonator_id', 'impersonation_audit_id']);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}

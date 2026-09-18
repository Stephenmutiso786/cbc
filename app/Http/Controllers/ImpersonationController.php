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
        abort_unless(auth()->user()->hasAnyRole(['super-admin', 'school-admin', 'headteacher', 'principal', 'deputy-headteacher', 'deputy']), 403);

        $users = User::query()
            ->where('id', '<>', auth()->id())
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'super-admin'))
            ->when(! auth()->user()->hasRole('super-admin'), fn ($query) => $query->where('school_id', auth()->user()->school_id))
            ->with('roles')
            ->orderBy('name')
            ->get();

        return view('admin.impersonate', ['users' => $users]);
    }

    public function start(Request $request, User $user)
    {
        abort_unless(auth()->user()->hasAnyRole(['super-admin', 'school-admin', 'headteacher', 'principal', 'deputy-headteacher', 'deputy']), 403);
        abort_if($user->is(auth()->user()) || $user->hasRole('super-admin'), 422, 'That account cannot be impersonated.');
        abort_if(! auth()->user()->hasRole('super-admin') && $user->school_id !== auth()->user()->school_id, 403, 'You can only impersonate users in your own school.');

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
        $originalUser = User::find($originalId);
        Auth::loginUsingId($originalId);
        $request->session()->forget(['impersonator_id', 'impersonation_audit_id']);
        $request->session()->regenerate();

        return redirect()->route($originalUser?->hasRole('super-admin') ? 'admin.platform-dashboard.index' : 'admin.dashboard');
    }
}

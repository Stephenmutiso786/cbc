<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PortalRedirector;
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

        // Do not reuse an intended URL from the administrator's session: it
        // may be the protected impersonation route or the guest login page,
        // both of which create a redirect loop for the target account.
        return redirect()->to(PortalRedirector::destinationFor($user));
    }

    public function stop(Request $request)
    {
        $originalId = $request->session()->get('impersonator_id');
        abort_unless($originalId && auth()->id() !== $originalId, 403);
        $auditId = $request->session()->get('impersonation_audit_id');
        if ($auditId) DB::table('impersonation_audits')->where('id', $auditId)->update(['ended_at' => now(), 'updated_at' => now()]);
        // While impersonating a school user, the tenant scope would hide the
        // original platform account (which intentionally has no school_id).
        // Load it explicitly without that scope before restoring the session.
        $originalUser = User::withoutSchoolScope()->findOrFail($originalId);
        Auth::login($originalUser);
        $request->session()->forget(['impersonator_id', 'impersonation_audit_id']);
        $request->session()->regenerate();

        return redirect()->to(PortalRedirector::destinationFor($originalUser));
    }
}

<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** Creates the standard learner portal account linked to a learner record. */
class StudentAccountProvisioner
{
    public function provision(Learner $learner): ?User
    {
        if ($learner->user_id) {
            return $learner->user;
        }

        $schoolId = $learner->school_id;
        if (! $schoolId) {
            return null;
        }

        $credentials = app(LoginCredentialService::class);
        $password = $credentials->defaultPasswordForLearner($learner);

        $user = User::create([
            'school_id' => $schoolId,
            'name' => $learner->full_name,
            // Internal unique address: learners sign in with admission number,
            // never with this implementation detail.
            'email' => 'learner-' . $learner->id . '@school-' . $schoolId . '.student.elimuhub.local',
            'password' => Hash::make($password),
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('learner');
        $learner->forceFill(['user_id' => $user->id])->saveQuietly();

        $credentials->sendLoginDetails($user->loadMissing('learner.guardians'), $password);

        return $user;
    }
}

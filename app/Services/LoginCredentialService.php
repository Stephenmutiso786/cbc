<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\Learner;
use App\Models\School;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoginCredentialService
{
    public function defaultPasswordForUser(User $user): string
    {
        $user->loadMissing(['learner.guardians', 'staffMember', 'guardian']);

        if ($user->learner) {
            return $this->defaultPasswordForLearner($user->learner);
        }

        if ($user->staffMember) {
            return $this->defaultPasswordForStaff($user->staffMember);
        }

        if ($user->guardian) {
            return $this->defaultPasswordForGuardian($user->guardian);
        }

        if ($user->school_id) {
            return $this->passwordForSerial($this->schoolPrefixForSchoolId($user->school_id), $user->id);
        }

        return $this->passwordForSerial($this->initialsFor($user->name), $user->id);
    }

    public function defaultPasswordForSchool(School $school): string
    {
        return $this->passwordForSerial($this->initialsFor($school->name), $school->id);
    }

    public function defaultPasswordForLearner(Learner $learner): string
    {
        return $this->passwordForSerial($this->schoolPrefixForSchoolId($learner->school_id), $learner->admission_number ?: $learner->id);
    }

    public function defaultPasswordForStaff(StaffMember $staff): string
    {
        return $this->passwordForSerial($this->schoolPrefixForSchoolId($staff->school_id), $staff->staff_number ?: $staff->id);
    }

    public function defaultPasswordForGuardian(Guardian $guardian): string
    {
        return $this->passwordForSerial($this->schoolPrefixForSchoolId($guardian->school_id), $guardian->id);
    }

    public function loginIdentifier(User $user): string
    {
        $user->loadMissing(['learner']);

        return $user->learner?->admission_number ?: $user->email;
    }

    public function buildLoginMessage(User $user, string $password): string
    {
        $user->loadMissing(['learner', 'staffMember', 'guardian']);

        $schoolName = $user->school?->name ?? config('school.name');
        $lines = [
            'ElimuHub login details for ' . $user->name,
            'School: ' . $schoolName,
            'Login: ' . $this->loginIdentifier($user),
            'Password: ' . $password,
            'Change the password immediately after first login.',
        ];

        return implode("\n", array_filter($lines));
    }

    public function sendLoginDetails(User $user, string $password, ?string $phone = null): array
    {
        $message = $this->buildLoginMessage($user, $password);
        $recipient = trim((string) ($phone ?: $this->resolvePhone($user)));

        if ($recipient === '') {
            return [
                'status' => 'not_sent',
                'recipient' => null,
                'message' => $message,
            ];
        }

        try {
            app(OlympusSmsService::class)->sendSms($recipient, $message);

            return [
                'status' => 'sent',
                'recipient' => $recipient,
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to send login credentials', [
                'user_id' => $user->id,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'recipient' => $recipient,
                'message' => $message,
            ];
        }
    }

    public function passwordForSerial(string $prefix, string|int|null $serialSource): string
    {
        return $this->formatPassword($prefix, $this->serialFrom($serialSource));
    }

    public function schoolPrefixForSchoolId(?int $schoolId): string
    {
        return $this->initialsFor($schoolId ? School::query()->find($schoolId)?->name : null);
    }

    private function resolvePhone(User $user): ?string
    {
        if ($user->staffMember?->phone_number) {
            return $user->staffMember->phone_number;
        }

        if ($user->guardian?->phone_number) {
            return $user->guardian->phone_number;
        }

        if ($user->learner) {
            $user->learner->loadMissing('guardians');

            return $user->learner->primaryGuardian?->phone_number
                ?? $user->learner->guardians->first()?->phone_number;
        }

        return null;
    }

    private function initialsFor(?string $name, string $fallback = 'SCH'): string
    {
        $words = preg_split('/[^[:alnum:]]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = collect($words)->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))->implode('');

        return Str::limit($initials ?: $fallback, 8, '');
    }

    private function serialFrom(string|int|null $serialSource): string
    {
        $value = trim((string) $serialSource);

        if ($value === '') {
            return '0001';
        }

        if (preg_match('/(\d+)(?!.*\d)/', $value, $matches)) {
            return $matches[1];
        }

        return preg_replace('/\D+/', '', $value) ?: '0001';
    }

    private function formatPassword(string $prefix, string $serial): string
    {
        $prefix = Str::upper(preg_replace('/\s+/', '', $prefix) ?: 'SCH');
        $serial = $serial === '' ? '0' : $serial;

        return $prefix . '-' . str_pad($serial, max(4, strlen($serial)), '0', STR_PAD_LEFT);
    }
}
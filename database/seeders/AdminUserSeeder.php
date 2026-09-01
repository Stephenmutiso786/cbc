<?php

namespace Database\Seeders;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@school.ac.ke'],
            ['name' => 'System Administrator', 'password' => Hash::make('Admin@1234'), 'email_verified_at' => now()]
        );
        $admin->assignRole('super-admin');

        $this->provisionStaff($admin, 'admin@school.ac.ke', [
            'user_id'         => $admin->id,
            'staff_number'    => 'STAFF-001',
            'first_name'      => 'System',
            'last_name'       => 'Administrator',
            'phone_number'    => '+254700000000',
            'gender'          => 'male',
            'employment_type' => 'permanent',
            'staff_type'      => 'non_teaching',
            'designation'     => 'System Administrator',
            'date_joined'     => now(),
        ]);

        // Headteacher
        $principal = User::firstOrCreate(
            ['email' => 'principal@school.ac.ke'],
            ['name' => 'School Headteacher', 'password' => Hash::make('Principal@1234'), 'email_verified_at' => now()]
        );
        $principal->syncRoles('headteacher');

        $this->provisionStaff($principal, 'principal@school.ac.ke', [
            'user_id'         => $principal->id,
            'staff_number'    => 'STAFF-002',
            'first_name'      => 'Jane',
            'last_name'       => 'Wanjiku',
            'phone_number'    => '+254711111111',
            'gender'          => 'female',
            'employment_type' => 'permanent',
            'staff_type'      => 'teaching',
            'designation'     => 'Headteacher',
            'date_joined'     => now()->subYears(5),
        ]);

        // Bursar
        $bursar = User::firstOrCreate(
            ['email' => 'bursar@school.ac.ke'],
            ['name' => 'School Bursar', 'password' => Hash::make('Bursar@1234'), 'email_verified_at' => now()]
        );
        $bursar->assignRole('bursar');

        $this->provisionStaff($bursar, 'bursar@school.ac.ke', [
            'user_id'         => $bursar->id,
            'staff_number'    => 'STAFF-003',
            'first_name'      => 'Peter',
            'last_name'       => 'Kamau',
            'phone_number'    => '+254722222222',
            'gender'          => 'male',
            'employment_type' => 'permanent',
            'staff_type'      => 'non_teaching',
            'designation'     => 'Bursar',
            'date_joined'     => now()->subYears(3),
        ]);

        $this->command->info('✅ Default users seeded:');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['super-admin', 'admin@school.ac.ke',     'Admin@1234'],
                ['principal',   'principal@school.ac.ke', 'Principal@1234'],
                ['bursar',      'bursar@school.ac.ke',    'Bursar@1234'],
            ]
        );
    }

    private function provisionStaff(User $user, string $email, array $attributes): void
    {
        // A previous interrupted seed may have left the staff number behind.
        $staff = StaffMember::withTrashed()->where('email', $email)->first()
            ?? StaffMember::withTrashed()->where('staff_number', $attributes['staff_number'])->first()
            ?? new StaffMember();

        if ($staff->trashed()) {
            $staff->restore();
        }

        $staff->fill(array_merge($attributes, [
            'email' => $email,
            'user_id' => $user->id,
        ]));
        $staff->save();
    }
}

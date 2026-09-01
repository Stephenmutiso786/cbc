<?php

namespace Database\Seeders;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ScreenshotTeachersSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = [
            ['David', 'Kamula', 'TCH-2026-0010', 'davidkamula@gmail.com', 'male'],
            ['Dennis', 'Wambua', 'TCH-2026-0008', 'dennhozyoka99@gmail.com', 'male'],
            ['Elizabeth', 'Nzyoki', 'TCH-2026-0011', 'elizabaika2016@gmail.com', 'female'],
            ['Evelyne', 'Kinyamasyo', 'TCH-2026-0003', 'mikayoevelyne@gmail.com', 'female'],
            ['Hellen', 'Kyalo', 'TCH-2026-0005', 'hellenwaeni@gmail.com', 'female'],
            ['Josephine', 'Kiio', 'TCH-2026-0014', 'josephinesimon@gmail.com', 'female'],
            ['Joyce', 'Mukoto', 'TCH-2026-0002', 'joycemukoto@gmail.com', 'female'],
            ['Kelvin', 'Paul', 'TCH-2026-0004', 'kelvinmutunga@gmail.com', 'male'],
            ['Mary', 'Mulee', 'TCH-2026-0013', 'marywanza446@gmail.com', 'female'],
            ['Pauline', 'Kinyosi', 'TCH-2026-0012', 'mwikalikinyosi@gmail.com', 'female'],
            ['Rose', 'Mwikali', 'TCH-2026-0009', 'rosemwikali2002@gmail.com', 'female'],
        ];

        foreach ($teachers as [$firstName, $lastName, $staffNumber, $email, $gender]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $firstName . ' ' . $lastName,
                    'password' => Hash::make(env('IMPORTED_TEACHER_TEMP_PASSWORD', 'ChangeMe@123')),
                    'email_verified_at' => now(),
                ],
            );
            $user->assignRole('teacher');

            StaffMember::updateOrCreate(
                ['staff_number' => $staffNumber],
                [
                    'user_id' => $user->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'gender' => $gender,
                    'staff_type' => 'teaching',
                    'designation' => 'Teacher',
                    'employment_type' => 'permanent',
                    'date_joined' => now()->toDateString(),
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info('Imported 11 screenshot teacher records.');
    }
}

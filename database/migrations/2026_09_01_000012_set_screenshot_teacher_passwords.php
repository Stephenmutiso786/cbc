<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        User::whereIn('email', [
            'davidkamula@gmail.com',
            'dennhozyoka99@gmail.com',
            'elizabaika2016@gmail.com',
            'mikayoevelyne@gmail.com',
            'hellenwaeni@gmail.com',
            'josephinesimon@gmail.com',
            'joycemukoto@gmail.com',
            'kelvinmutunga@gmail.com',
            'marywanza446@gmail.com',
            'mwikalikinyosi@gmail.com',
            'rosemwikali2002@gmail.com',
        ])->update([
            'password' => Hash::make('Teacher@2026'),
        ]);
    }

    public function down(): void
    {
        // Passwords are intentionally not reverted to a shared temporary value.
    }
};

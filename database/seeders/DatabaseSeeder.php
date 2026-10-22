<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['superadmin', 'admin', 'Super Admin'],
            ['staff', 'staff', 'John Smith'],
            ['student', 'student', 'Ethan Parker'],
            ['nusrat', 'student', 'Nusrat Jahan'],
            ['parent', 'parent', 'Amelia Parker'],
        ];

        foreach ($accounts as [$username, $role, $name]) {
            User::updateOrCreate(['username' => $username], [
                'name' => $name,
                'email' => "{$username}@borno.test",
                'role' => $role,
                'password' => Hash::make('Demo@1234'),
            ]);
        }

        $this->call(PortalSeeder::class);
    }
}

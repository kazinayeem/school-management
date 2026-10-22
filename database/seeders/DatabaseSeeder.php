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
            ['staff', 'staff', 'Staff Member'],
            ['student', 'student', 'Student'],
            ['parent', 'parent', 'Parent'],
        ];

        foreach ($accounts as [$username, $role, $name]) {
            User::updateOrCreate(['username' => $username], [
                'name' => $name,
                'email' => "{$username}@borno.test",
                'role' => $role,
                'password' => Hash::make('Demo@1234'),
            ]);
        }
    }
}

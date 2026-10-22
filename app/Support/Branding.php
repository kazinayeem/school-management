<?php

namespace App\Support;

class Branding
{
    public static function data(): array
    {
        return [
            'name' => config('app.name', 'ReadySchool'),
            'logo' => asset('images/logo.png'),
            'logoWhite' => asset('images/logo-white.png'),
            'favicon' => asset('favicon.ico'),
            'theme' => [
                'primary' => '#b31299',
            ],
            'demo' => [
                'admin' => [
                    'username' => 'superadmin',
                    'password' => 'Demo@1234',
                    'email' => 'superadmin@sms.local',
                    'name' => 'Super Admin',
                    'role' => 'Super Admin',
                ],
                'teacher' => [
                    'username' => 'jsmith',
                    'password' => 'Demo@1234',
                    'email' => 'teacher@sms.local',
                    'name' => 'Md. Jahangir Alam',
                    'role' => 'Teacher',
                ],
                'staff' => [
                    'username' => 'accountant',
                    'password' => 'Demo@1234',
                    'email' => 'staff@sms.local',
                    'name' => 'Shafiqul Islam',
                    'role' => 'Staff',
                ],
                'student' => [
                    'username' => 'student',
                    'password' => 'Demo@1234',
                    'email' => 'student@sms.local',
                    'name' => 'Tanvir Ahmed',
                    'role' => 'Student',
                ],
                'parent' => [
                    'username' => 'parent',
                    'password' => 'Demo@1234',
                    'email' => 'parent@sms.local',
                    'name' => 'Md. Kamal Uddin Ahmed',
                    'role' => 'Parent',
                ],
            ],
            'languages' => [
                ['code' => 'en', 'name' => 'English', 'flag' => '🇬🇧', 'is_rtl' => false, 'is_default' => true],
                ['code' => 'ar', 'name' => 'العربية', 'flag' => '🇸🇦', 'is_rtl' => true, 'is_default' => false],
                ['code' => 'bn', 'name' => 'বাংলা', 'flag' => '🇧🇩', 'is_rtl' => false, 'is_default' => false],
            ],
        ];
    }
}

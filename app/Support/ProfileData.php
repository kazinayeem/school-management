<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** What a person sees and edits on their own profile page, whatever their role. */
class ProfileData
{
    public static function for(User $user): array
    {
        $address = $user->address;
        $dob = $user->date_of_birth;
        if ($user->role === 'student') {
            $p = DB::table('student_profiles')->where('user_id', $user->id)->first();
            $address ??= $p->address ?? null;
            $dob ??= $p->date_of_birth ?? null;
        } elseif (in_array($user->role, ['teacher', 'staff'], true)) {
            $address ??= DB::table('staff_profiles')->where('user_id', $user->id)->value('address');
        }

        return [
            'id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'role' => $user->role, 'email' => $user->email, 'phone' => $user->phone,
            'alt_phone' => $user->alt_phone, 'address' => $address, 'emergency_name' => $user->emergency_name, 'emergency_phone' => $user->emergency_phone,
            'blood_group' => $user->blood_group, 'date_of_birth' => $dob ? substr((string) $dob, 0, 10) : null, 'photo' => Photos::url($user->photo),
        ];
    }
}

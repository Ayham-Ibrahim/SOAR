<?php

namespace Database\Seeders;

use App\Models\ParentAccountRequest;
use App\Models\ParentModel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ParentAccountRequestSeeder extends Seeder
{
    /**
     * One approved parent linked to two children, plus two other students
     * with a pending request each — demo data for the parent-account-request
     * feature.
     */
    public function run(): void
    {
        $childOne = User::firstOrCreate(
            ['phone' => '+963911111111'],
            [
                'name' => 'ابنة تجريبية أولى',
                'password' => Hash::make('password'),
                'phone_verified_at' => now(),
            ]
        );

        $childTwo = User::firstOrCreate(
            ['phone' => '+963911111112'],
            [
                'name' => 'ابن تجريبي ثاني',
                'password' => Hash::make('password'),
                'phone_verified_at' => now(),
            ]
        );

        $parent = ParentModel::firstOrCreate(
            ['phone' => '+963922222222'],
            [
                'name' => 'ولي أمر تجريبي',
                'password' => Hash::make('password'),
                'phone_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $parent->students()->syncWithoutDetaching([$childOne->id, $childTwo->id]);

        $requesterOne = User::firstOrCreate(
            ['phone' => '+963911111113'],
            [
                'name' => 'طالب صاحب طلب أول',
                'password' => Hash::make('password'),
                'phone_verified_at' => now(),
            ]
        );

        $requesterTwo = User::firstOrCreate(
            ['phone' => '+963911111114'],
            [
                'name' => 'طالب صاحب طلب ثاني',
                'password' => Hash::make('password'),
                'phone_verified_at' => now(),
            ]
        );

        ParentAccountRequest::firstOrCreate(
            ['requested_by_student_id' => $requesterOne->id, 'status' => 'pending'],
            [
                'parent_name' => 'ولي أمر قيد المراجعة الأول',
                'parent_phone' => '+963933333333',
                'password' => Hash::make('password'),
            ]
        );

        ParentAccountRequest::firstOrCreate(
            ['requested_by_student_id' => $requesterTwo->id, 'status' => 'pending'],
            [
                'parent_name' => 'ولي أمر قيد المراجعة الثاني',
                'parent_phone' => '+963933333334',
                'password' => Hash::make('password'),
            ]
        );
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\ParentModel;
use App\Models\School;
use App\Models\StudyType;
use App\Models\SubCategory;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Creates (or refreshes) the App Store review accounts: a student and a
 * parent linked to each other, both flagged is_reviewer so they can sign in
 * from any number of devices, with the student subscribed to every course.
 * Re-run it after adding courses to open those as well.
 */
class CreateReviewAccount extends Command
{
    protected $signature = 'review:account
        {--student-phone=+963911111111}
        {--parent-phone=+963922222222}
        {--password=Review@2026}
        {--days=365 : How long the course subscriptions last}';

    protected $description = 'Create or refresh the App Store review student + parent accounts with every course open';

    public function handle(): int
    {
        $password = (string) $this->option('password');
        $expiresAt = now()->addDays((int) $this->option('days'));

        $subCategory = SubCategory::query()->orderBy('id')->first();
        $school = School::query()->orderBy('id')->first();

        [$student, $parent, $courses] = DB::transaction(function () use ($password, $expiresAt, $subCategory, $school) {
            $student = User::updateOrCreate(
                ['phone' => $this->option('student-phone')],
                [
                    'name' => 'App Review Student',
                    'password' => $password,
                    'is_reviewer' => true,
                    'phone_verified_at' => now(),
                    'governorate_id' => $school?->governorate_id,
                    'school_id' => $school?->id,
                    'category_id' => $subCategory?->category_id,
                    'sub_category_id' => $subCategory?->id,
                    'study_type_id' => StudyType::query()->orderBy('id')->value('id'),
                ],
            );

            $parent = ParentModel::updateOrCreate(
                ['phone' => $this->option('parent-phone')],
                [
                    'name' => 'App Review Parent',
                    'password' => $password,
                    'is_reviewer' => true,
                    'is_active' => true,
                    'phone_verified_at' => now(),
                ],
            );

            $parent->students()->syncWithoutDetaching([$student->id]);

            $courses = Course::query()->pluck('id');

            foreach ($courses as $courseId) {
                Subscription::updateOrCreate(
                    ['student_id' => $student->id, 'course_id' => $courseId],
                    [
                        'source' => 'direct',
                        'starts_at' => now(),
                        'expires_at' => $expiresAt,
                        'is_active' => true,
                        'revoked_at' => null,
                        'revoked_by' => null,
                        'revocation_reason' => null,
                    ],
                );
            }

            return [$student, $parent, $courses];
        });

        $this->info("Opened {$courses->count()} course(s) until {$expiresAt->toDateString()}.");
        $this->table(['Account', 'type', 'Phone', 'Password'], [
            ['Student', 'user', $student->phone, $password],
            ['Parent', 'parent', $parent->phone, $password],
        ]);

        return self::SUCCESS;
    }
}

<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Offer;
use App\Models\Subscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SubscriptionSeeder extends Seeder
{
    /**
     * 1 active + 1 expired direct subscription, plus 1 offer bundling 2
     * courses with 1 already-approved offer subscription (both bundled
     * courses granted).
     */
    public function run(): void
    {
        $courses = Course::take(3)->get();

        if ($courses->count() < 3) {
            return;
        }

        [$directCourse, $offerCourseOne, $offerCourseTwo] = $courses;

        $activeStudent = User::firstOrCreate(
            ['phone' => '+963911119991'],
            [
                'name' => 'طالب مشترك فعّال',
                'password' => Hash::make('password'),
                'phone_verified_at' => now(),
            ]
        );

        $expiredStudent = User::firstOrCreate(
            ['phone' => '+963911119992'],
            [
                'name' => 'طالب اشتراكه منتهي',
                'password' => Hash::make('password'),
                'phone_verified_at' => now(),
            ]
        );

        Subscription::firstOrCreate(
            ['student_id' => $activeStudent->id, 'course_id' => $directCourse->id],
            [
                'source' => 'direct',
                'starts_at' => now()->subDays(10),
                'expires_at' => now()->addDays(20),
                'is_active' => true,
            ]
        );

        Subscription::firstOrCreate(
            ['student_id' => $expiredStudent->id, 'course_id' => $directCourse->id],
            [
                'source' => 'direct',
                'starts_at' => now()->subDays(40),
                'expires_at' => now()->subDays(10),
                'is_active' => false,
            ]
        );

        $offer = Offer::firstOrCreate(
            ['title' => 'عرض تجريبي: فيزياء وكيمياء'],
            [
                'description' => 'اشترك بمادتين دفعة واحدة بسعر مخفّض',
                'price' => 75000,
                'offer_starts_at' => now()->subDays(5),
                'offer_ends_at' => now()->addDays(5),
                'access_duration_days' => 365,
                'is_active' => true,
            ]
        );
        $offer->courses()->syncWithoutDetaching([$offerCourseOne->id, $offerCourseTwo->id]);

        $offerStudent = User::firstOrCreate(
            ['phone' => '+963911119993'],
            [
                'name' => 'طالب مشترك بعرض',
                'password' => Hash::make('password'),
                'phone_verified_at' => now(),
            ]
        );

        $offerRequest = SubscriptionRequest::firstOrCreate(
            ['student_id' => $offerStudent->id, 'offer_id' => $offer->id],
            [
                'receipt_image' => '/storage/subscription-receipts/seed-placeholder.jpg',
                'amount' => $offer->price,
                'status' => 'approved',
                'reviewed_at' => now(),
            ]
        );

        foreach ([$offerCourseOne, $offerCourseTwo] as $course) {
            Subscription::firstOrCreate(
                ['student_id' => $offerStudent->id, 'course_id' => $course->id],
                [
                    'source' => 'offer',
                    'offer_id' => $offer->id,
                    'subscription_request_id' => $offerRequest->id,
                    'starts_at' => now(),
                    'expires_at' => now()->addDays($offer->access_duration_days),
                    'is_active' => true,
                ]
            );
        }
    }
}

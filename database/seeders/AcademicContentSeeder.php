<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Course;
use App\Models\Governorate;
use App\Models\Lesson;
use App\Models\School;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\Video;
use Illuminate\Database\Seeder;

class AcademicContentSeeder extends Seeder
{
    /**
     * Sample provinces/schools plus a full branch → category → subject → course →
     * unit → lesson → video tree, for local development and demos.
     */
    public function run(): void
    {
        $damascus = Governorate::where('name', 'دمشق')->firstOrFail();
        $aleppo = Governorate::where('name', 'حلب')->firstOrFail();

        School::create(['governorate_id' => $damascus->id, 'name' => 'مدرسة الرسالة']);
        School::create(['governorate_id' => $damascus->id, 'name' => 'مدرسة الفارابي']);
        School::create(['governorate_id' => $aleppo->id, 'name' => 'مدرسة الأمل']);

        $branches = [
            'التاسع' => [
                'العلوم' => ['الفيزياء', 'الكيمياء'],
                'اللغات' => ['اللغة العربية', 'اللغة الإنكليزية'],
            ],
            'البكالوريا العلمي' => [
                'العلوم' => ['الفيزياء', 'الرياضيات'],
                'اللغات' => ['اللغة العربية', 'اللغة الإنكليزية'],
            ],
        ];

        $branchOrder = 1;

        foreach ($branches as $branchName => $categories) {
            $branch = Branch::create([
                'name' => $branchName,
                'order' => $branchOrder++,
                'is_active' => true,
            ]);

            $categoryOrder = 1;

            foreach ($categories as $categoryName => $subjects) {
                $category = Category::create([
                    'branch_id' => $branch->id,
                    'name' => $categoryName,
                    'order' => $categoryOrder++,
                    'is_active' => true,
                ]);

                $subjectOrder = 1;

                foreach ($subjects as $subjectName) {
                    $subject = Subject::create([
                        'category_id' => $category->id,
                        'name' => $subjectName,
                        'order' => $subjectOrder++,
                        'is_active' => true,
                    ]);

                    $course = Course::create([
                        'subject_id' => $subject->id,
                        'title' => 'دورة '.$subjectName,
                        'description' => 'دورة شاملة في مادة '.$subjectName,
                        'price' => 50000,
                        'discount_price' => 40000,
                        'subscription_days' => 180,
                        'free_videos_count' => 1,
                        'allow_download' => true,
                        'is_active' => true,
                    ]);

                    $this->seedUnits($course);
                }
            }
        }
    }

    private function seedUnits(Course $course): void
    {
        for ($u = 1; $u <= 2; $u++) {
            $unit = Unit::create([
                'course_id' => $course->id,
                'title' => 'الوحدة '.$u,
                'order' => $u,
            ]);

            for ($l = 1; $l <= 2; $l++) {
                $lesson = Lesson::create([
                    'unit_id' => $unit->id,
                    'title' => 'الدرس '.$l,
                    'order' => $l,
                    'is_free' => $l === 1,
                ]);

                Video::create([
                    'lesson_id' => $lesson->id,
                    'title' => 'فيديو الدرس '.$l,
                    'url' => 'https://example.com/videos/placeholder.mp4',
                    'order' => 1,
                    'is_free' => $lesson->is_free,
                    'is_downloadable' => true,
                ]);
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\Governorate;
use App\Models\Lesson;
use App\Models\School;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Unit;
use App\Models\Video;
use Illuminate\Database\Seeder;

class AcademicContentSeeder extends Seeder
{
    /**
     * Sample provinces/schools plus a full
     * category -> sub_category -> subject -> { course, units -> lessons -> video } tree,
     * for local development and demos.
     */
    public function run(): void
    {
        $damascus = Governorate::where('name', 'دمشق')->firstOrFail();
        $aleppo = Governorate::where('name', 'حلب')->firstOrFail();

        School::create(['governorate_id' => $damascus->id, 'name' => 'مدرسة الرسالة']);
        School::create(['governorate_id' => $damascus->id, 'name' => 'مدرسة الفارابي']);
        School::create(['governorate_id' => $aleppo->id, 'name' => 'مدرسة الأمل']);

        $teacher = Teacher::create([
            'name' => 'الأستاذ أحمد الخطيب',
            'bio' => 'مدرّس ذو خبرة طويلة في التعليم الثانوي',
            'is_active' => true,
        ]);

        $tree = [
            'ثانوي' => [
                'البكالوريا العلمي' => [
                    'الفيزياء' => null,
                    'الكيمياء' => null,
                ],
                'البكالوريا الأدبي' => [
                    'اللغة العربية' => null,
                    'التاريخ' => null,
                ],
            ],
            'إعدادي' => [
                'التاسع' => [
                    'الرياضيات' => null,
                    'العلوم' => null,
                ],
                'الثامن' => [
                    'الرياضيات' => null,
                    'اللغة الإنكليزية' => null,
                ],
            ],
        ];

        $categoryOrder = 1;

        foreach ($tree as $categoryName => $subCategories) {
            $category = Category::create([
                'name' => $categoryName,
                'order' => $categoryOrder++,
                'is_active' => true,
            ]);

            $subCategoryOrder = 1;

            foreach ($subCategories as $subCategoryName => $subjects) {
                $subCategory = SubCategory::create([
                    'category_id' => $category->id,
                    'name' => $subCategoryName,
                    'order' => $subCategoryOrder++,
                    'is_active' => true,
                ]);

                $subjectOrder = 1;

                foreach (array_keys($subjects) as $subjectName) {
                    $subject = Subject::create([
                        'sub_category_id' => $subCategory->id,
                        'name' => $subjectName,
                        'order' => $subjectOrder++,
                        'is_active' => true,
                    ]);

                    $units = [];
                    for ($u = 1; $u <= 3; $u++) {
                        $units[] = Unit::create([
                            'subject_id' => $subject->id,
                            'title' => 'الوحدة '.$u,
                            'order' => $u,
                        ]);
                    }

                    $course = Course::create([
                        'subject_id' => $subject->id,
                        'teacher_id' => $teacher->id,
                        'title' => 'دورة '.$subjectName,
                        'description' => 'دورة شاملة في مادة '.$subjectName,
                        'price' => 50000,
                        'discount_price' => 40000,
                        'subscription_days' => 180,
                        'free_videos_count' => 1,
                        'allow_download' => true,
                        'is_active' => true,
                    ]);

                    $this->seedLessons($course, $units);
                }
            }
        }
    }

    /**
     * @param  array<int, Unit>  $units
     */
    private function seedLessons(Course $course, array $units): void
    {
        for ($l = 1; $l <= 4; $l++) {
            $unit = $units[($l - 1) % count($units)];

            $lesson = Lesson::create([
                'course_id' => $course->id,
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

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Notification;
use App\Models\ParentModel;
use App\Models\StudyType;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentBroadcastTargetingTest extends TestCase
{
    use RefreshDatabase;

    private SubCategory $thirdScientific;

    private SubCategory $secondSecondary;

    private StudyType $institute;

    private StudyType $school;

    private int $phoneSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // No FCM credentials in tests: pushes are skipped, the stored
        // notification (the in-app inbox) is what we assert on.
        putenv('FIREBASE_CREDENTIALS_FILE=missing-test-credentials.json');

        $category = Category::create(['name' => 'ثانوي', 'order' => 1]);
        $this->thirdScientific = SubCategory::create([
            'category_id' => $category->id,
            'name' => 'الثالث الثانوي العلمي',
            'order' => 1,
        ]);
        $this->secondSecondary = SubCategory::create([
            'category_id' => $category->id,
            'name' => 'الثاني الثانوي',
            'order' => 2,
        ]);
        $this->institute = StudyType::create(['name' => 'معهد أجيال دمر']);
        $this->school = StudyType::create(['name' => 'مدرسة']);
    }

    private function student(string $name, ?SubCategory $grade = null, ?StudyType $studyType = null): User
    {
        return User::factory()->create([
            'name' => $name,
            'sub_category_id' => $grade?->id,
            'study_type_id' => $studyType?->id,
        ]);
    }

    private function parentOf(string $name, User ...$students): ParentModel
    {
        $parent = ParentModel::create([
            'name' => $name,
            'phone' => '+9639111'.str_pad((string) ++$this->phoneSequence, 5, '0', STR_PAD_LEFT),
            'password' => 'password',
            'phone_verified_at' => now(),
        ]);
        $parent->students()->attach(collect($students)->pluck('id'));

        return $parent;
    }

    private function broadcast(array $payload): TestResponse
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]), ['dashboard']);

        return $this->postJson('/api/admin/broadcast-notifications', $payload + [
            'title' => 'إشعار',
            'content' => 'نص الإشعار',
        ]);
    }

    /**
     * @return list<string> names of the recipients that got it, by kind
     */
    private function notified(string $class): array
    {
        return UserNotification::query()
            ->where('notifiable_type', (new $class)->getMorphClass())
            ->pluck('notifiable_id')
            ->map(fn ($id) => $class::find($id)?->name)
            ->all();
    }

    private function notifiedParents(): array
    {
        return $this->notified(ParentModel::class);
    }

    private function notifiedStudents(): array
    {
        return $this->notified(User::class);
    }

    public function test_targeting_a_grade_reaches_only_the_parents_of_that_grade(): void
    {
        $thirdGrader = $this->student('طالب ثالث', $this->thirdScientific);
        $secondGrader = $this->student('طالب ثاني', $this->secondSecondary);
        $targetParent = $this->parentOf('ولي أمر الثالث', $thirdGrader);
        $otherParent = $this->parentOf('ولي أمر الثاني', $secondGrader);

        $this->broadcast([
            'target_types' => ['parents'],
            'sub_category_id' => $this->thirdScientific->id,
        ])->assertCreated();

        $this->assertSame(['ولي أمر الثالث'], $this->notifiedParents());
        $this->assertSame([], $this->notifiedStudents());

        $notification = Notification::first();
        $this->assertSame('completed', $notification->status);
        $this->assertSame(1, $notification->sent_count);

        // Visible in the parent app, and only for the targeted parent.
        Sanctum::actingAs($targetParent, ['access-api']);
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'إشعار')
            ->assertJsonPath('data.0.type', 'broadcast');

        Sanctum::actingAs($otherParent, ['access-api']);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_targeting_a_study_type_reaches_only_the_parents_of_that_study_type(): void
    {
        $this->parentOf('ولي أمر المعهد', $this->student('طالب معهد', null, $this->institute));
        $this->parentOf('ولي أمر المدرسة', $this->student('طالب مدرسة', null, $this->school));

        $this->broadcast([
            'target_types' => ['parents'],
            'study_type_id' => $this->institute->id,
        ])->assertCreated();

        $this->assertSame(['ولي أمر المعهد'], $this->notifiedParents());
    }

    public function test_specific_parents_or_specific_students_can_be_targeted(): void
    {
        $studentA = $this->student('طالب أ');
        $studentB = $this->student('طالب ب');
        $parentA = $this->parentOf('ولي أ', $studentA);
        $this->parentOf('ولي ب', $studentB);
        $this->parentOf('ولي ج', $this->student('طالب ج'));

        $this->broadcast(['target_types' => ['parents'], 'parent_ids' => [$parentA->id]])->assertCreated();
        $this->assertSame(['ولي أ'], $this->notifiedParents());

        UserNotification::query()->delete();

        $this->broadcast(['target_types' => ['parents'], 'student_ids' => [$studentB->id]])->assertCreated();
        $this->assertSame(['ولي ب'], $this->notifiedParents());
    }

    public function test_two_filters_together_must_both_match_the_same_student(): void
    {
        $this->parentOf('ولي المطابق', $this->student('مطابق', $this->thirdScientific, $this->institute));
        $this->parentOf('ولي نوع مختلف', $this->student('صف مطابق', $this->thirdScientific, $this->school));
        $this->parentOf('ولي صف مختلف', $this->student('نوع مطابق', $this->secondSecondary, $this->institute));

        $this->broadcast([
            'target_types' => ['parents'],
            'sub_category_id' => $this->thirdScientific->id,
            'study_type_id' => $this->institute->id,
        ])->assertCreated();

        $this->assertSame(['ولي المطابق'], $this->notifiedParents());
    }

    /**
     * Chosen parents are added to the filtered group, not intersected with it —
     * otherwise "these parents" plus "third grade" would reach nobody.
     */
    public function test_chosen_parents_are_added_to_the_filtered_group(): void
    {
        $this->parentOf('ولي الثالث', $this->student('ثالث', $this->thirdScientific));
        $chosen = $this->parentOf('ولي مختار', $this->student('ثاني', $this->secondSecondary));
        $this->parentOf('ولي بعيد', $this->student('طالب آخر', $this->secondSecondary));

        $this->broadcast([
            'target_types' => ['parents'],
            'sub_category_id' => $this->thirdScientific->id,
            'parent_ids' => [$chosen->id],
        ])->assertCreated();

        $this->assertEqualsCanonicalizing(['ولي الثالث', 'ولي مختار'], $this->notifiedParents());
    }

    public function test_a_broadcast_can_target_students_and_parents_at_once(): void
    {
        $student = $this->student('طالب ثالث', $this->thirdScientific);
        $this->parentOf('ولي الأمر', $student);
        $this->parentOf('ولي بعيد', $this->student('طالب ثاني', $this->secondSecondary));

        $this->broadcast([
            'target_types' => ['students', 'parents'],
            'sub_category_id' => $this->thirdScientific->id,
        ])->assertCreated();

        $notification = Notification::first();
        $this->assertSame(['students', 'parents'], $notification->target_types);
        $this->assertSame(['طالب ثالث'], $this->notifiedStudents());
        $this->assertSame(['ولي الأمر'], $this->notifiedParents());
        $this->assertSame(2, $notification->sent_count);
    }

    public function test_without_filters_every_parent_is_reached(): void
    {
        $this->parentOf('ولي أول', $this->student('طالب أول', $this->thirdScientific));
        $this->parentOf('ولي ثانٍ', $this->student('طالب ثانٍ', $this->secondSecondary));

        $this->broadcast(['target_types' => ['parents']])->assertCreated();

        $this->assertEqualsCanonicalizing(['ولي أول', 'ولي ثانٍ'], $this->notifiedParents());
        $this->assertSame([], $this->notifiedStudents());
    }
}

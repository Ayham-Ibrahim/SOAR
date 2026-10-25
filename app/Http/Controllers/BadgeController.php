<?php

namespace App\Http\Controllers;

use App\Models\ParentModel;
use App\Models\User;
use App\Services\NewContentService;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;

/**
 * Every badge the app shows, in one call: unread notifications, new news,
 * and new results — the student's own, or per child for a parent.
 */
class BadgeController extends Controller
{
    public function __construct(
        private readonly UserNotificationService $notifications,
        private readonly NewContentService $newContent
    ) {
    }

    public function index(Request $request)
    {
        $viewer = $request->user();

        $badges = [
            'notifications_unread_count' => $this->notifications->unreadCount($viewer),
            'news' => $this->newContent->summary($this->newContent->newNewsCount($viewer)),
        ];

        if ($viewer instanceof ParentModel) {
            $badges['children'] = $viewer->students()->get(['users.id', 'users.name'])
                ->map(fn (User $child) => [
                    'student_id' => $child->id,
                    'name' => $child->name,
                    'results' => $this->newContent->summary($this->newContent->newResultsCount($viewer, $child)),
                ])
                ->values();
        } else {
            $badges['results'] = $this->newContent->summary($this->newContent->newResultsCount($viewer, $viewer));
        }

        return $this->success($badges, 'تم جلب المؤشرات بنجاح');
    }
}

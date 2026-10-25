<?php

namespace App\Http\Controllers;

use App\Services\UserNotificationService;
use Illuminate\Http\Request;

/**
 * The in-app inbox of whoever is signed in — student, parent, or admin
 * (all authenticate through the same sanctum guard).
 */
class NotificationController extends Controller
{
    public function __construct(private readonly UserNotificationService $notificationService)
    {
    }

    public function index(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'تم جلب الإشعارات بنجاح',
            'data' => $this->notificationService->listFor($request->user()),
            'unread_count' => $this->notificationService->unreadCount($request->user()),
        ]);
    }

    /**
     * For the bell badge, without fetching the whole inbox.
     */
    public function unreadCount(Request $request)
    {
        return $this->success(
            ['unread_count' => $this->notificationService->unreadCount($request->user())],
            'تم جلب عدد الإشعارات غير المقروءة بنجاح'
        );
    }

    public function markRead(Request $request, int $id)
    {
        $notification = $this->notificationService->markRead($request->user(), $id);

        return $this->success([
            'notification' => $notification,
            'unread_count' => $this->notificationService->unreadCount($request->user()),
        ], 'تم تعليم الإشعار كمقروء');
    }

    public function markAllRead(Request $request)
    {
        $marked = $this->notificationService->markAllRead($request->user());

        return $this->success([
            'marked_count' => $marked,
            'unread_count' => 0,
        ], 'تم تعليم جميع الإشعارات كمقروءة');
    }
}

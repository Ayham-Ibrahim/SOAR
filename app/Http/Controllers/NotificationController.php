<?php

namespace App\Http\Controllers;

use App\Services\UserNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly UserNotificationService $notificationService)
    {
    }

    public function index(Request $request)
    {
        return $this->success(
            $this->notificationService->listFor($request->user()),
            'تم جلب الإشعارات بنجاح'
        );
    }

}
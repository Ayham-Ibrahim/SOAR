<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Models\Setting;
use App\Services\Admin\SettingService;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settingService)
    {
    }

    public function index()
    {
        return $this->success($this->settingService->list(), 'تم جلب الإعدادات بنجاح');
    }

    public function show(string $key)
    {
        $setting = Setting::where('key', $key)->firstOrFail();

        return $this->success($setting, 'تم جلب الإعداد بنجاح');
    }

    /**
     * Upsert: creates the key if it doesn't exist yet, updates it otherwise.
     */
    public function update(UpdateSettingRequest $request, string $key)
    {
        $setting = $this->settingService->update($key, $request->validated('value'));

        return $this->success($setting, 'تم تحديث الإعداد بنجاح');
    }
}

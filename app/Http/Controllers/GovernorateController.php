<?php

namespace App\Http\Controllers;

use App\Models\Governorate;

class GovernorateController extends Controller
{
    public function index()
    {
        return $this->success(Governorate::orderBy('name')->get(), 'تم جلب المحافظات بنجاح');
    }
}

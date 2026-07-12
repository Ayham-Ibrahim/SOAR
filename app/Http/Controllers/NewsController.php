<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Services\Admin\NewsService;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(private readonly NewsService $newsService)
    {
    }

    public function index(Request $request)
    {
        $news = $this->newsService->list($request->integer('per_page', 15));

        return $this->paginate($news, 'تم جلب الأخبار بنجاح');
    }

    public function show(News $news)
    {
        return $this->success($news, 'تم جلب بيانات الخبر بنجاح');
    }
}

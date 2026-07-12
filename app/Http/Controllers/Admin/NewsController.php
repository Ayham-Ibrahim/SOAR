<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreNewsRequest;
use App\Http\Requests\Admin\UpdateNewsRequest;
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

    public function store(StoreNewsRequest $request)
    {
        $news = $this->newsService->create($request->validated());

        return $this->success($news, 'تم إنشاء الخبر بنجاح', 201);
    }

    public function show(News $news)
    {
        return $this->success($news, 'تم جلب بيانات الخبر بنجاح');
    }

    public function update(UpdateNewsRequest $request, News $news)
    {
        $news = $this->newsService->update($news, $request->validated());

        return $this->success($news, 'تم تحديث بيانات الخبر بنجاح');
    }

    public function destroy(News $news)
    {
        $this->newsService->delete($news);

        return $this->success([], 'تم حذف الخبر بنجاح');
    }
}

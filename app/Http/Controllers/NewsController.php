<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\SeenMarker;
use App\Services\Admin\NewsService;
use App\Services\NewContentService;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function __construct(
        private readonly NewsService $newsService,
        private readonly NewContentService $newContent
    ) {
    }

    /**
     * Public; when signed in, additional_data says how many are new since
     * the viewer last opened the news (POST /news/seen).
     */
    public function index(Request $request)
    {
        $news = $this->newsService->list($request->integer('per_page', 15));
        $viewer = $request->user('sanctum');

        if (! $viewer) {
            return $this->paginate($news, 'تم جلب الأخبار بنجاح');
        }

        return $this->paginateWithData(
            $news,
            $this->newContent->summary($this->newContent->newNewsCount($viewer)),
            'تم جلب الأخبار بنجاح'
        );
    }

    public function seen(Request $request)
    {
        $this->newContent->markSeen($request->user(), SeenMarker::NEWS);

        return $this->success($this->newContent->summary(0), 'تم تعليم الأخبار كمشاهدة');
    }

    public function show(News $news)
    {
        return $this->success($news, 'تم جلب بيانات الخبر بنجاح');
    }
}

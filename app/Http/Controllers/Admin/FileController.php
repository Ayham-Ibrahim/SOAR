<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFileRequest;
use App\Http\Requests\Admin\UpdateFileRequest;
use App\Models\File as LessonFile;
use App\Services\Admin\FileService;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(private readonly FileService $fileService)
    {
    }

    public function index(Request $request)
    {
        $files = $this->fileService->list(
            $request->integer('lesson_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($files, 'تم جلب الملفات بنجاح');
    }

    public function store(StoreFileRequest $request)
    {
        $file = $this->fileService->create($request->validated());

        return $this->success($file, 'تم إنشاء الملف بنجاح', 201);
    }

    public function show(LessonFile $file)
    {
        return $this->success($file, 'تم جلب بيانات الملف بنجاح');
    }

    public function update(UpdateFileRequest $request, LessonFile $file)
    {
        $file = $this->fileService->update($file, $request->validated());

        return $this->success($file, 'تم تحديث بيانات الملف بنجاح');
    }

    public function destroy(LessonFile $file)
    {
        $this->fileService->delete($file);

        return $this->success([], 'تم حذف الملف بنجاح');
    }
}

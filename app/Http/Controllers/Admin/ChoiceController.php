<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChoiceRequest;
use App\Http\Requests\Admin\UpdateChoiceRequest;
use App\Models\Choice;
use App\Services\Admin\ChoiceService;
use Illuminate\Http\Request;

class ChoiceController extends Controller
{
    public function __construct(private readonly ChoiceService $choiceService)
    {
    }

    public function index(Request $request)
    {
        $choices = $this->choiceService->list(
            $request->integer('question_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($choices, 'تم جلب الخيارات بنجاح');
    }

    public function store(StoreChoiceRequest $request)
    {
        $choice = $this->choiceService->create($request->validated());

        return $this->success($choice, 'تم إنشاء الخيار بنجاح', 201);
    }

    public function show(Choice $choice)
    {
        return $this->success($choice, 'تم جلب بيانات الخيار بنجاح');
    }

    public function update(UpdateChoiceRequest $request, Choice $choice)
    {
        $choice = $this->choiceService->update($choice, $request->validated());

        return $this->success($choice, 'تم تحديث بيانات الخيار بنجاح');
    }

    public function destroy(Choice $choice)
    {
        $this->choiceService->delete($choice);

        return $this->success([], 'تم حذف الخيار بنجاح');
    }
}

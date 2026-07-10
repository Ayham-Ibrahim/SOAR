<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\Admin\UnitService;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function __construct(private readonly UnitService $unitService)
    {
    }

    public function index(Request $request)
    {
        $units = $this->unitService->list(
            $request->integer('course_id') ?: null,
            $request->integer('per_page', 15)
        );

        return $this->paginate($units, 'تم جلب الوحدات بنجاح');
    }

    public function store(StoreUnitRequest $request)
    {
        $unit = $this->unitService->create($request->validated());

        return $this->success($unit, 'تم إنشاء الوحدة بنجاح', 201);
    }

    public function show(Unit $unit)
    {
        return $this->success($unit->load('lessons'), 'تم جلب بيانات الوحدة بنجاح');
    }

    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $unit = $this->unitService->update($unit, $request->validated());

        return $this->success($unit, 'تم تحديث بيانات الوحدة بنجاح');
    }

    public function destroy(Unit $unit)
    {
        $this->unitService->delete($unit);

        return $this->success([], 'تم حذف الوحدة بنجاح (وكل الدروس والفيديوهات والملفات التابعة لها)');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\Admin\UnitService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function __construct(private readonly UnitService $unitService)
    {
    }

    public function index(Request $request)
    {
        $units = $this->unitService->list(
            $request->integer('subject_id') ?: null,
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
        try {
            $this->unitService->delete($unit);
        } catch (QueryException) {
            return $this->error('لا يمكن حذف الوحدة لوجود دروس مرتبطة بها', 409);
        }

        return $this->success([], 'تم حذف الوحدة بنجاح');
    }
}

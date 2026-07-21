<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreParentRequest;
use App\Http\Requests\Admin\SyncParentStudentsRequest;
use App\Http\Requests\Admin\UpdateParentRequest;
use App\Models\ParentModel;
use App\Services\Admin\ParentService;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    public function __construct(private readonly ParentService $parentService)
    {
    }

    public function index(Request $request)
    {
        $parents = $this->parentService->list($request->integer('per_page', 15));

        return $this->paginate($parents, 'تم جلب أولياء الأمور بنجاح');
    }

    public function store(StoreParentRequest $request)
    {
        $parent = $this->parentService->create($request->validated());

        return $this->success($parent, 'تم إنشاء ولي الأمر بنجاح', 201);
    }

    public function show(ParentModel $parent)
    {
        return $this->success($parent, 'تم جلب بيانات ولي الأمر بنجاح');
    }

    public function update(UpdateParentRequest $request, ParentModel $parent)
    {
        $parent = $this->parentService->update($parent, $request->validated());

        return $this->success($parent, 'تم تحديث بيانات ولي الأمر بنجاح');
    }

    public function destroy(ParentModel $parent)
    {
        $this->parentService->delete($parent);

        return $this->success([], 'تم حذف ولي الأمر بنجاح');
    }

    public function addStudents(SyncParentStudentsRequest $request, ParentModel $parent)
    {
        $parent = $this->parentService->attachStudents($parent, $request->validated('student_ids'));

        return $this->success($parent, 'تم ربط الطلاب بولي الأمر بنجاح');
    }

    public function removeStudent(ParentModel $parent, int $studentId)
    {
        $this->parentService->unlinkStudent($parent, $studentId);

        return $this->success([], 'تم إلغاء ربط الطالب بولي الأمر بنجاح');
    }
}

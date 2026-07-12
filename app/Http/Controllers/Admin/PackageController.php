<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePackageRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Models\Package;
use App\Services\Admin\PackageService;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function __construct(private readonly PackageService $packageService)
    {
    }

    public function index(Request $request)
    {
        $packages = $this->packageService->list($request->integer('per_page', 15));

        return $this->paginate($packages, 'تم جلب الباقات بنجاح');
    }

    public function store(StorePackageRequest $request)
    {
        $package = $this->packageService->create($request->validated());

        return $this->success($package, 'تم إنشاء الباقة بنجاح', 201);
    }

    public function show(Package $package)
    {
        return $this->success($package->load('courses', 'offers'), 'تم جلب بيانات الباقة بنجاح');
    }

    public function update(UpdatePackageRequest $request, Package $package)
    {
        $package = $this->packageService->update($package, $request->validated());

        return $this->success($package, 'تم تحديث بيانات الباقة بنجاح');
    }

    public function destroy(Package $package)
    {
        $this->packageService->delete($package);

        return $this->success([], 'تم حذف الباقة بنجاح');
    }
}

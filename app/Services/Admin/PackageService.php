<?php

namespace App\Services\Admin;

use App\Models\Package;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class PackageService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Package::query()->with('courses')->latest()->paginate($perPage);
    }

    public function create(array $data): Package
    {
        $package = Package::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'image' => isset($data['image']) ? FileStorage::storeFile($data['image'], 'packages', 'img') : null,
            'price' => $data['price'],
            'discount_price' => $data['discount_price'] ?? null,
            'subscription_days' => $data['subscription_days'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (isset($data['course_ids'])) {
            $package->courses()->sync($data['course_ids']);
        }

        return $package->load('courses');
    }

    public function update(Package $package, array $data): Package
    {
        $package->update([
            'title' => $data['title'] ?? $package->title,
            'description' => $data['description'] ?? $package->description,
            'image' => isset($data['image'])
                ? FileStorage::fileExists($data['image'], $package->image, 'packages', 'img')
                : $package->image,
            'price' => $data['price'] ?? $package->price,
            'discount_price' => $data['discount_price'] ?? $package->discount_price,
            'subscription_days' => $data['subscription_days'] ?? $package->subscription_days,
            'is_active' => $data['is_active'] ?? $package->is_active,
        ]);

        if (isset($data['course_ids'])) {
            $package->courses()->sync($data['course_ids']);
        }

        return $package->fresh()->load('courses');
    }

    public function delete(Package $package): void
    {
        $package->delete();
    }
}

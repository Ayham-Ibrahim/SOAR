<?php

namespace App\Services\Admin;

use App\Models\Offer;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class OfferService
{
    public function list(?int $packageId = null, int $perPage = 15): LengthAwarePaginator
    {
        return Offer::query()
            ->with('package')
            ->when($packageId, fn ($query) => $query->where('package_id', $packageId))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Offer
    {
        return Offer::create([
            'package_id' => $data['package_id'],
            'title' => $data['title'],
            'image' => isset($data['image']) ? FileStorage::storeFile($data['image'], 'offers', 'img') : null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Offer $offer, array $data): Offer
    {
        $offer->update([
            'package_id' => $data['package_id'] ?? $offer->package_id,
            'title' => $data['title'] ?? $offer->title,
            'image' => isset($data['image'])
                ? FileStorage::fileExists($data['image'], $offer->image, 'offers', 'img')
                : $offer->image,
            'discount_type' => $data['discount_type'] ?? $offer->discount_type,
            'discount_value' => $data['discount_value'] ?? $offer->discount_value,
            'starts_at' => $data['starts_at'] ?? $offer->starts_at,
            'ends_at' => $data['ends_at'] ?? $offer->ends_at,
            'is_active' => $data['is_active'] ?? $offer->is_active,
        ]);

        return $offer->fresh();
    }

    public function delete(Offer $offer): void
    {
        $offer->delete();
    }
}

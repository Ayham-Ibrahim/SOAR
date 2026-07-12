<?php

namespace App\Services\Admin;

use App\Models\Advertisement;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class AdvertisementService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Advertisement::query()->paginate($perPage);
    }

    public function create(array $data): Advertisement
    {
        return Advertisement::create([
            'title' => $data['title'] ?? null,
            'image' => FileStorage::storeFile($data['image'], 'advertisements', 'img'),
            'link' => $data['link'] ?? null,
            'order' => $data['order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Advertisement $advertisement, array $data): Advertisement
    {
        $advertisement->update([
            'title' => $data['title'] ?? $advertisement->title,
            'image' => isset($data['image'])
                ? FileStorage::fileExists($data['image'], $advertisement->image, 'advertisements', 'img')
                : $advertisement->image,
            'link' => $data['link'] ?? $advertisement->link,
            'order' => $data['order'] ?? $advertisement->order,
            'is_active' => $data['is_active'] ?? $advertisement->is_active,
        ]);

        return $advertisement->fresh();
    }

    public function delete(Advertisement $advertisement): void
    {
        $advertisement->delete();
    }
}

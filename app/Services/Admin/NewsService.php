<?php

namespace App\Services\Admin;

use App\Models\News;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class NewsService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return News::query()->latest()->paginate($perPage);
    }

    public function create(array $data): News
    {
        return News::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'image' => isset($data['image']) ? FileStorage::storeFile($data['image'], 'news', 'img') : null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(News $news, array $data): News
    {
        $news->update([
            'title' => $data['title'] ?? $news->title,
            'body' => $data['body'] ?? $news->body,
            'image' => isset($data['image'])
                ? FileStorage::fileExists($data['image'], $news->image, 'news', 'img')
                : $news->image,
            'is_active' => $data['is_active'] ?? $news->is_active,
        ]);

        return $news->fresh();
    }

    public function delete(News $news): void
    {
        $news->delete();
    }
}

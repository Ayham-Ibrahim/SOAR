<?php

namespace App\Services\Admin;

use App\Models\Offer;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class OfferService
{
    /**
     * Each offer comes with its courses (teacher and subject included) and
     * courses_count. With $catalogOnly (the student app, or a dashboard
     * dropdown): only active offers that still have a course in the catalog,
     * and of each offer only those courses, counted the same way.
     */
    public function list(int $perPage = 15, bool $catalogOnly = false): LengthAwarePaginator
    {
        return Offer::query()
            ->when($catalogOnly, fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('courses', fn ($query) => $query->inCatalog()))
            ->with(['courses' => fn ($query) => $this->offerCourses($query, $catalogOnly)->with(['teacher', 'subject'])])
            ->withCount(['courses' => fn ($query) => $this->offerCourses($query, $catalogOnly), 'subscriptions'])
            ->latest()
            ->paginate($perPage);
    }

    public function details(Offer $offer, bool $catalogOnly = false): Offer
    {
        return $offer
            ->load(['courses' => fn ($query) => $this->offerCourses($query, $catalogOnly)->with(['teacher', 'subject'])])
            ->loadCount(['courses' => fn ($query) => $this->offerCourses($query, $catalogOnly)]);
    }

    private function offerCourses($query, bool $catalogOnly)
    {
        return $query->when($catalogOnly, fn ($query) => $query->inCatalog());
    }

    public function create(array $data): Offer
    {
        $offer = Offer::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'image' => isset($data['image']) ? FileStorage::storeFile($data['image'], 'offers', 'img') : null,
            'price' => $data['price'],
            'offer_starts_at' => $data['offer_starts_at'],
            'offer_ends_at' => $data['offer_ends_at'],
            'access_duration_days' => $data['access_duration_days'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $offer->courses()->attach($data['course_ids']);

        return $offer->fresh('courses');
    }

    public function update(Offer $offer, array $data): Offer
    {
        $offer->update([
            'title' => $data['title'] ?? $offer->title,
            'description' => $data['description'] ?? $offer->description,
            'image' => isset($data['image'])
                ? FileStorage::fileExists($data['image'], $offer->image, 'offers', 'img')
                : $offer->image,
            'price' => $data['price'] ?? $offer->price,
            'offer_starts_at' => $data['offer_starts_at'] ?? $offer->offer_starts_at,
            'offer_ends_at' => $data['offer_ends_at'] ?? $offer->offer_ends_at,
            'access_duration_days' => $data['access_duration_days'] ?? $offer->access_duration_days,
            'is_active' => $data['is_active'] ?? $offer->is_active,
        ]);

        if (isset($data['course_ids'])) {
            $offer->courses()->sync($data['course_ids']);
        }

        return $offer->fresh('courses');
    }

    public function delete(Offer $offer): void
    {
        $offer->delete();
    }
}

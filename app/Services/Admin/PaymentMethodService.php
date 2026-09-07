<?php

namespace App\Services\Admin;

use App\Models\PaymentMethod;
use App\Services\FileStorage;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentMethodService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return PaymentMethod::query()->latest()->paginate($perPage);
    }

    public function create(array $data): PaymentMethod
    {
        return PaymentMethod::create([
            'option_name' => $data['option_name'] ?? null,
            'person_name' => $data['person_name'] ?? null,
            'person_phone' => $data['person_phone'] ?? null,
            'qr_code' => isset($data['qr_code'])
                ? FileStorage::storeFile($data['qr_code'], 'payment-methods', 'img')
                : null,
            'location' => $data['location'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ]);
    }

    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $paymentMethod->update([
            'option_name' => $data['option_name'] ?? $paymentMethod->option_name,
            'person_name' => $data['person_name'] ?? $paymentMethod->person_name,
            'person_phone' => $data['person_phone'] ?? $paymentMethod->person_phone,
            'qr_code' => isset($data['qr_code'])
                ? FileStorage::fileExists($data['qr_code'], $paymentMethod->qr_code, 'payment-methods', 'img')
                : $paymentMethod->qr_code,
            'location' => $data['location'] ?? $paymentMethod->location,
            'is_active' => $data['is_active'] ?? $paymentMethod->is_active,
        ]);

        return $paymentMethod->fresh();
    }

    public function delete(PaymentMethod $paymentMethod): void
    {
        if ($paymentMethod->qr_code) {
            FileStorage::deleteFile($paymentMethod->qr_code);
        }

        $paymentMethod->delete();
    }

    public function activeList(int $perPage = 15): LengthAwarePaginator
    {
        return PaymentMethod::query()
            ->where(function ($query) {
                $query->where('is_active', true)->orWhereNull('is_active');
            })
            ->latest()
            ->paginate($perPage);
    }
}

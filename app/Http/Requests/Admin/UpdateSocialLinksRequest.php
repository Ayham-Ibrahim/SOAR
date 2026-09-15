<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSocialLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
    * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'facebook' => ['nullable', 'url', 'starts_with:https://'],
            'instagram' => ['nullable', 'url', 'starts_with:https://'],
            'youtube' => ['nullable', 'url', 'starts_with:https://'],
            'tiktok' => ['nullable', 'url', 'starts_with:https://'],
            'telegram' => ['nullable', 'url', 'starts_with:https://'],
            'whatsapp' => ['nullable', 'url', 'starts_with:https://'],
            'x' => ['nullable', 'url', 'starts_with:https://'],
        ];
    }
}
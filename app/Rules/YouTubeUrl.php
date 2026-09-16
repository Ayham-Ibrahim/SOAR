<?php

namespace App\Rules;

use App\Support\YouTubeVideo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class YouTubeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || YouTubeVideo::idFrom($value) === null) {
            $fail('حقل :attribute يجب أن يكون رابط فيديو يوتيوب صالحاً.');
        }
    }
}

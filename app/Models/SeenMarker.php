<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @see \App\Services\NewContentService
 */
class SeenMarker extends Model
{
    public const NEWS = 'news';
    public const RESULTS = 'results';

    protected $fillable = [
        'viewer_type', 'viewer_id', 'section', 'student_id', 'seen_at',
    ];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime'];
    }

    public function viewer(): MorphTo
    {
        return $this->morphTo();
    }
}

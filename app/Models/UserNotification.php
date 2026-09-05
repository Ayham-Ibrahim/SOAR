<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserNotification extends Model
{
    use HasFactory;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'notifiable_type', 'notifiable_id', 'title', 'body', 'data',
    ];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
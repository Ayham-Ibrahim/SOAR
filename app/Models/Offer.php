<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchasable bundle of courses. offer_starts_at/offer_ends_at only gate
 * WHEN a student may subscribe — they do not affect how long a purchased
 * course stays open (that's access_duration_days, counted from the moment
 * of purchase, independent of the offer window).
 */
class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'image',
        'price',
        'offer_starts_at',
        'offer_ends_at',
        'access_duration_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'offer_starts_at' => 'datetime',
            'offer_ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'offer_course');
    }

    public function subscriptionRequests(): HasMany
    {
        return $this->hasMany(SubscriptionRequest::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isPurchasableNow(): bool
    {
        return $this->is_active
            && now()->between($this->offer_starts_at, $this->offer_ends_at);
    }
}

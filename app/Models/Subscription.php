<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The grant that actually opens content — see App\Services\CourseAccess for
 * the single access-check method. A grant is live iff an admin hasn't
 * revoked it and expires_at > now() (scopeActive); is_active is refreshed
 * nightly (subscriptions:expire) for reporting only.
 *
 * Revoked grants are never deleted: revoked_at/revoked_by/revocation_reason
 * keep the audit trail, and status reports active / expired / revoked.
 */
class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'source',
        'offer_id',
        'subscription_request_id',
        'starts_at',
        'expires_at',
        'is_active',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
    ];

    protected $appends = ['status'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRequest::class, 'subscription_request_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Grants that open content right now — the one definition CourseAccess
     * and every "active subscriber" list or count share.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && (bool) $this->expires_at?->isFuture();
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->revoked_at !== null => 'revoked',
            (bool) $this->expires_at?->isFuture() => 'active',
            default => 'expired',
        });
    }
}

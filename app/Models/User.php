<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasDevices;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasDevices, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'governorate_id',
        'school_id',
        'gender',
        'age',
        'avatar',
        'is_admin',
        'sham_code',
        'sham_image',
        'password',
        'phone_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isPhoneVerified(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    /**
     * Informational only (reports/statistics) — never used to gate or filter
     * content. The platform is open: every student can browse everything.
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * Informational only (reports/statistics) — never used to gate or filter
     * content. The platform is open: every student can browse everything.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentModel::class, 'parent_student', 'student_id', 'parent_id');
    }

    public function parentAccountRequests(): HasMany
    {
        return $this->hasMany(ParentAccountRequest::class, 'requested_by_student_id');
    }
}

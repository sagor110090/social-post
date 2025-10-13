<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'platform_id',
        'username',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'is_active',
    ];

    protected $casts = [
        'access_token' => 'encrypted:array',
        'refresh_token' => 'encrypted:array',
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scheduledPosts(): HasMany
    {
        return $this->hasMany(ScheduledPost::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(PostAnalytics::class);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function getPlatformDisplayName(): string
    {
        return match($this->platform) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'twitter' => 'X (Twitter)',
            default => ucfirst($this->platform),
        };
    }
}

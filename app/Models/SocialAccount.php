<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'platform',
        'platform_id',
        'username',
        'display_name',
        'email',
        'avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'additional_data',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted:array',
        'refresh_token' => 'encrypted:array',
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'additional_data' => 'array',
        'last_synced_at' => 'datetime',
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

    /**
     * Get the provider name (alias for platform).
     */
    public function getProviderAttribute()
    {
        return $this->platform;
    }

    /**
     * Set the provider name (alias for platform).
     */
    public function setProviderAttribute($value)
    {
        $this->attributes['platform'] = $value;
    }

    /**
     * Get the provider ID (alias for platform_id).
     */
    public function getProviderIdAttribute()
    {
        return $this->platform_id;
    }

    /**
     * Set the provider ID (alias for platform_id).
     */
    public function setProviderIdAttribute($value)
    {
        $this->attributes['platform_id'] = $value;
    }

 


}

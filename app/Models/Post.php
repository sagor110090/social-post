<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'title',
        'content',
        'hashtags',
        'image_path',
        'link',
        'image_url',
        'media_urls',
        'status',
        'platforms',
        'platform_results',
        'published_at',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'platforms' => 'array',
        'platform_results' => 'array',
        'media_urls' => 'array',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scheduledPosts(): HasMany
    {
        return $this->hasMany(ScheduledPost::class);
    }

    public function scheduledPost(): HasOne
    {
        return $this->hasOne(ScheduledPost::class)->latest();
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(PostAnalytics::class);
    }

    public function webhookEventProcessing(): HasMany
    {
        return $this->hasMany(WebhookEventProcessing::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function getHashtagsString(): string
    {
        if (empty($this->hashtags)) {
            return '';
        }

        return collect($this->hashtags)->map(fn ($tag) => '#' . $tag)->implode(' ');
    }

    public function getExcerpt(int $length = 100): string
    {
        return Str::limit(strip_tags($this->content), $length);
    }

    /**
     * Get platform-specific post ID from platform results.
     */
    public function getPlatformPostId(string $platform): ?string
    {
        return data_get($this->platform_results, "{$platform}.platform_post_id");
    }

    /**
     * Find webhook events related to this post.
     */
    public function relatedWebhookEvents(): HasMany
    {
        return WebhookEvent::where(function ($query) {
            foreach ($this->platforms ?? [] as $platform) {
                $platformPostId = $this->getPlatformPostId($platform);
                if ($platformPostId) {
                    $query->orWhere(function ($q) use ($platform, $platformPostId) {
                        $q->where('platform', $platform)
                          ->where('object_id', $platformPostId);
                    });
                }
            }
        });
    }
}

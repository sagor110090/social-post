<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function analytics(): HasMany
    {
        return $this->hasMany(PostAnalytics::class);
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
        return str_limit(strip_tags($this->content), $length);
    }
}

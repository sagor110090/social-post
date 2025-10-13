<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'path',
        'url',
        'mime_type',
        'size',
        'metadata',
        'processed_images',
    ];

    protected $casts = [
        'metadata' => 'array',
        'processed_images' => 'array',
    ];

    /**
     * Get the user that owns the media.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the media is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the media is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Get the file extension.
     */
    public function getExtension(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    /**
     * Get the file size in human readable format.
     */
    public function getHumanReadableSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the optimal image URL for a specific size and platform.
     */
    public function getOptimalUrl(string $size = 'medium', string $platform = 'general'): ?string
    {
        if (!$this->isImage()) {
            return $this->url;
        }

        // Check for platform-specific size first
        $platformSizeKey = "{$platform}_{$size}";
        if (isset($this->processed_images[$platformSizeKey])) {
            return $this->processed_images[$platformSizeKey];
        }

        // Fall back to general size
        if (isset($this->processed_images[$size])) {
            return $this->processed_images[$size];
        }

        // Fall back to original URL
        return $this->url;
    }

    /**
     * Get the platform this media was uploaded for.
     */
    public function getPlatform(): string
    {
        return $this->metadata['platform'] ?? 'general';
    }

    /**
     * Get the original filename.
     */
    public function getOriginalName(): string
    {
        return $this->metadata['original_name'] ?? $this->filename;
    }

    /**
     * Get image dimensions if available.
     */
    public function getDimensions(): ?array
    {
        if (!$this->isImage()) {
            return null;
        }

        return [
            'width' => $this->metadata['width'] ?? null,
            'height' => $this->metadata['height'] ?? null,
        ];
    }

    /**
     * Scope to get only images.
     */
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    /**
     * Scope to get only videos.
     */
    public function scopeVideos($query)
    {
        return $query->where('mime_type', 'like', 'video/%');
    }

    /**
     * Scope to get media for a specific platform.
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->whereJsonContains('metadata->platform', $platform);
    }

    /**
     * Scope to get recent media.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
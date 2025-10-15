<?php

namespace App\Services\Social;

use Illuminate\Support\Str;

class ContentFormatterService
{
    /**
     * Format content for specific social media platforms.
     */
    public function formatForPlatform(string $content, string $platform): string
    {
        return match ($platform) {
            'facebook' => $this->formatForFacebook($content),
            'instagram' => $this->formatForInstagram($content),
            'linkedin' => $this->formatForLinkedIn($content),
            'twitter' => $this->formatForTwitter($content),
            default => $content,
        };
    }

    /**
     * Format content for Facebook.
     * Facebook doesn't support markdown, so we need to convert formatting.
     */
    private function formatForFacebook(string $content): string
    {
        // Remove markdown bold formatting
        $content = preg_replace('/\*\*(.*?)\*\*/', '$1', $content);
        
        // Convert numbered lists to bullet points
        $content = preg_replace('/^\d+\.\s+(.+)$/m', '• $1', $content);
        
        // Ensure UTF-8 encoding
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        
        return trim($content);
    }

    /**
     * Format content for Instagram.
     * Instagram has limited formatting support, focus on readability.
     */
    private function formatForInstagram(string $content): string
    {
        // Remove markdown formatting
        $content = preg_replace('/\*\*(.*?)\*\*/', '$1', $content);
        
        // Ensure UTF-8 encoding
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        
        return trim($content);
    }

    /**
     * Format content for LinkedIn.
     * LinkedIn supports some formatting but plain text works best.
     */
    private function formatForLinkedIn(string $content): string
    {
        // Remove markdown formatting
        $content = preg_replace('/\*\*(.*?)\*\*/', '$1', $content);
        
        // Ensure UTF-8 encoding
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        
        return trim($content);
    }

    /**
     * Format content for Twitter.
     * Twitter is plain text focused, remove all formatting.
     */
    private function formatForTwitter(string $content): string
    {
        // Remove markdown formatting
        $content = preg_replace('/\*\*(.*?)\*\*/', '$1', $content);
        
        // Convert numbered lists to simple text
        $content = preg_replace('/^\d+\.\s+(.+)$/m', '$1', $content);
        
        // Ensure UTF-8 encoding
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        
        return trim($content);
    }

    /**
     * Convert common emoji shortcodes to actual emojis.
     */
    private function convertEmojiShortcodes(string $content): string
    {
        // Disabled to prevent UTF-8 encoding issues
        // Emojis should be used directly in the content
        return $content;
    }

    /**
     * Add platform-specific hashtags formatting.
     */
    public function formatHashtags(array $hashtags, string $platform): string
    {
        if (empty($hashtags)) {
            return '';
        }

        $formattedHashtags = collect($hashtags)->map(fn($tag) => '#' . $tag);

        return match ($platform) {
            'instagram' => $formattedHashtags->implode(' '), // Instagram likes more hashtags
            'facebook' => $formattedHashtags->take(5)->implode(' '), // Facebook: fewer hashtags
            'linkedin' => $formattedHashtags->take(3)->implode(' '), // LinkedIn: professional hashtags
            'twitter' => $formattedHashtags->take(2)->implode(' '), // Twitter: very limited space
            default => $formattedHashtags->implode(' '),
        };
    }

    /**
     * Get optimal hashtag count for platform.
     */
    public function getOptimalHashtagCount(string $platform): int
    {
        return match ($platform) {
            'instagram' => 10, // Instagram allows up to 30, but 10-15 is optimal
            'facebook' => 5,   // Facebook: 3-5 hashtags work best
            'linkedin' => 3,   // LinkedIn: 2-3 professional hashtags
            'twitter' => 2,    // Twitter: limited by character count
            default => 5,
        };
    }

    /**
     * Test method to validate UTF-8 encoding (for debugging)
     */
    public function testFormat(string $content, string $platform): array
    {
        $formatted = $this->formatForPlatform($content, $platform);
        
        return [
            'original' => $content,
            'formatted' => $formatted,
            'is_utf8' => mb_check_encoding($formatted, 'UTF-8'),
            'length' => strlen($formatted),
            'platform' => $platform,
        ];
    }
}
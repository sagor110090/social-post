<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class OpenAIService
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
        $this->baseUrl = 'https://api.groq.com/openai/v1';
    }

    public function generatePost(array $options): array
    {
        try {
            $prompt = $this->buildPrompt($options);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/chat/completions', [
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a social media expert who creates engaging content for various platforms. Always return valid JSON format with "content" and "hashtags" fields.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'model' => 'qwen/qwen3-32b',
                'temperature' => 0.6,
                'max_completion_tokens' => 4096,
                'top_p' => 0.95,
                'stream' => false,
                'reasoning_effort' => 'default',
                'stop' => null,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Groq API Error: ' . $response->body());
            }

            $content = $response->json('choices.0.message.content');

            // Try to parse as JSON first, fallback to text parsing
            $parsedContent = $this->parseResponse($content);

            return [
                'content' => $this->extractContent($parsedContent['content'] ?? $content),
                'hashtags' => $options['include_hashtags']
                    ? $this->extractHashtags(is_array($parsedContent['hashtags'] ?? [])
                        ? implode(' ', $parsedContent['hashtags'] ?? [])
                        : ($parsedContent['hashtags'] ?? $content))
                    : [],
                'image_prompt' => $options['include_image']
                    ? $this->generateImagePrompt($options['prompt'])
                    : null,
            ];

        } catch (\Exception $e) {
            Log::error('Groq API Error: ' . $e->getMessage());

            return [
                'content' => 'Unable to generate content at the moment. Please try again.',
                'hashtags' => [],
                'image_prompt' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function buildPrompt(array $options): string
    {
        $platformRules = [
            'facebook' => 'Keep it conversational and engaging, suitable for Facebook\'s algorithm. Use emojis if appropriate.',
            'instagram' => 'Make it visually appealing with emojis, perfect for Instagram captions. Include relevant hashtags.',
            'linkedin' => 'Keep it professional and industry-focused for LinkedIn. Use business-appropriate language.',
            'twitter' => 'Keep it concise and impactful for Twitter (280 characters max). Use relevant hashtags.',
        ];

        $toneInstructions = [
            'professional' => 'Use professional language, avoid slang, maintain formal tone.',
            'casual' => 'Use conversational language, be friendly and approachable.',
            'friendly' => 'Use warm, welcoming language with positive tone.',
            'humorous' => 'Include appropriate humor, be witty but not offensive.',
        ];

        $prompt = "Create a {$options['tone']} social media post about: {$options['prompt']}. ";
        $prompt .= $platformRules[$options['platform']] . " ";
        $prompt .= $toneInstructions[$options['tone']] . " ";

        if ($options['include_hashtags']) {
            $prompt .= "Include 3-5 relevant hashtags. ";
        }

        $prompt .= "Return the response in JSON format with 'content' and 'hashtags' fields.";

        return $prompt;
    }

    private function parseResponse(string $content): array
    {
        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        return ['content' => $content, 'hashtags' => []];
    }

    private function extractHashtags(string|array $content): array
    {
        // Convert to string if it's an array
        if (is_array($content)) {
            $content = implode(' ', $content);
        }

        // Extract hashtags from content
        preg_match_all('/#(\w+)/', $content, $matches);
        $hashtags = $matches[1] ?? [];

        // If no hashtags found, generate some based on content
        if (empty($hashtags)) {
            $words = str_word_count(strtolower($content), 1);
            $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them', 'my', 'your', 'his', 'her', 'its', 'our', 'their'];

            $relevantWords = array_diff($words, $commonWords);
            $hashtags = array_slice($relevantWords, 0, 5);
        }

        return array_unique(array_map('strtolower', $hashtags));
    }

    private function extractContent(string $content): string
    {
        // Remove hashtags from content for clean text
        $content = preg_replace('/#(\w+)/', '', $content);

        // Clean up extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        // Remove any JSON artifacts
        $content = preg_replace('/^\{?\s*["\']?content["\']?\s*:\s*["\']?/', '', $content);
        $content = preg_replace('/["\']?\s*(,|\})?\s*$/', '', $content);

        return $content;
    }

    private function generateImagePrompt(string $originalPrompt): string
    {
        return "Create a professional, modern social media image related to: {$originalPrompt}.
                Style: clean, minimalist, business-oriented, suitable for social media platforms.
                Colors: professional and eye-catching.
                Composition: centered subject with clean background.";
    }

    public function generateImageIdeas(string $prompt, int $count = 3): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/chat/completions', [
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a creative director who generates image ideas for social media posts. Always return valid JSON format with an "ideas" array.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Generate {$count} creative image ideas for a social media post about: {$prompt}.
                        Each idea should be descriptive and suitable for social media.
                        Return as JSON with 'ideas' array containing strings."
                    ]
                ],
                'model' => 'qwen/qwen3-32b',
                'temperature' => 0.8,
                'max_completion_tokens' => 4096,
                'top_p' => 0.95,
                'stream' => false,
                'reasoning_effort' => 'default',
                'stop' => null,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Groq API Error: ' . $response->body());
            }

            $content = $response->json('choices.0.message.content');

            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $json = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && isset($json['ideas'])) {
                    return $json['ideas'];
                }
            }

            return [
                "Professional image related to {$prompt}",
                "Creative visual representation of {$prompt}",
                "Modern design showcasing {$prompt}"
            ];

        } catch (\Exception $e) {
            Log::error('Groq Image Ideas Error: ' . $e->getMessage());

            return [
                "Professional image related to {$prompt}",
                "Creative visual representation of {$prompt}",
                "Modern design showcasing {$prompt}"
            ];
        }
    }

    public function improveContent(string $content, string $platform, string $tone = 'professional'): array
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->post($this->baseUrl . '/chat/completions', [
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a social media expert who improves existing content for better engagement. Always return valid JSON format with "improved_content" and "suggestions" fields.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Improve this social media content for {$platform} with a {$tone} tone: '{$content}'.
                        Make it more engaging and effective.
                        Return as JSON with 'improved_content' and 'suggestions' array."
                    ]
                ],
                'model' => 'qwen/qwen3-32b',
                'temperature' => 0.6,
                'max_completion_tokens' => 4096,
                'top_p' => 0.95,
                'stream' => false,
                'reasoning_effort' => 'default',
                'stop' => null,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Groq API Error: ' . $response->body());
            }

            $responseContent = $response->json('choices.0.message.content');

            if (preg_match('/\{.*\}/s', $responseContent, $matches)) {
                $json = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return [
                        'improved_content' => $json['improved_content'] ?? $content,
                        'suggestions' => $json['suggestions'] ?? [],
                    ];
                }
            }

            return [
                'improved_content' => $content,
                'suggestions' => ['Content could not be improved automatically'],
            ];

        } catch (\Exception $e) {
            Log::error('Groq Content Improvement Error: ' . $e->getMessage());

            return [
                'improved_content' => $content,
                'suggestions' => ['Unable to improve content at the moment'],
            ];
        }
    }
}

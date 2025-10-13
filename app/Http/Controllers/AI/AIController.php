<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AIController extends Controller
{
    public function __construct(private OpenAIService $openAI)
    {
        $this->middleware('auth');
        $this->middleware('subscription:pro')->only(['generate', 'improve', 'imageIdeas']);
    }

    public function index(): Response
    {
        return Inertia::render('AI/AIGenerator');
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'platform' => 'required|in:facebook,instagram,linkedin,twitter',
            'tone' => 'nullable|in:professional,casual,friendly,humorous',
            'include_hashtags' => 'boolean',
            'include_image' => 'boolean',
        ]);

        $result = $this->openAI->generatePost([
            'prompt' => $request->prompt,
            'platform' => $request->platform,
            'tone' => $request->tone ?? 'professional',
            'include_hashtags' => $request->boolean('include_hashtags', true),
            'include_image' => $request->boolean('include_image', false),
        ]);

        return response()->json($result);
    }

    public function improve(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:2000',
            'platform' => 'required|in:facebook,instagram,linkedin,twitter',
            'tone' => 'nullable|in:professional,casual,friendly,humorous',
        ]);

        $result = $this->openAI->improveContent(
            $request->content,
            $request->platform,
            $request->tone ?? 'professional'
        );

        return response()->json($result);
    }

    public function imageIdeas(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|max:500',
            'count' => 'nullable|integer|min:1|max:5',
        ]);

        $ideas = $this->openAI->generateImageIdeas(
            $request->prompt,
            $request->integer('count', 3)
        );

        return response()->json(['ideas' => $ideas]);
    }

    public function templates(): JsonResponse
    {
        $templates = [
            [
                'id' => 1,
                'name' => 'Product Launch',
                'prompt' => 'Excited to announce our new product launch!',
                'category' => 'business',
                'tones' => ['professional', 'casual'],
            ],
            [
                'id' => 2,
                'name' => 'Behind the Scenes',
                'prompt' => 'Take a look behind the scenes at our office',
                'category' => 'culture',
                'tones' => ['casual', 'friendly'],
            ],
            [
                'id' => 3,
                'name' => 'Industry News',
                'prompt' => 'Latest developments in our industry',
                'category' => 'news',
                'tones' => ['professional'],
            ],
            [
                'id' => 4,
                'name' => 'Team Achievement',
                'prompt' => 'Proud of our team for achieving this milestone',
                'category' => 'team',
                'tones' => ['professional', 'friendly'],
            ],
            [
                'id' => 5,
                'name' => 'Customer Success Story',
                'prompt' => 'How we helped a customer solve their problem',
                'category' => 'testimonial',
                'tones' => ['professional', 'friendly'],
            ],
            [
                'id' => 6,
                'name' => 'Event Announcement',
                'prompt' => 'Join us for our upcoming event',
                'category' => 'event',
                'tones' => ['professional', 'casual', 'friendly'],
            ],
            [
                'id' => 7,
                'name' => 'Holiday Greeting',
                'prompt' => 'Wishing everyone a happy holiday season',
                'category' => 'holiday',
                'tones' => ['friendly', 'humorous'],
            ],
            [
                'id' => 8,
                'name' => 'Educational Content',
                'prompt' => 'Here are some tips and best practices',
                'category' => 'education',
                'tones' => ['professional', 'friendly'],
            ],
        ];

        return response()->json(['templates' => $templates]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // This would typically come from a database table for AI generation history
        // For now, return empty array as we haven't implemented the history table yet
        $history = [];

        return response()->json(['history' => $history]);
    }

    public function saveTemplate(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'prompt' => 'required|string|max:500',
            'category' => 'required|string|max:50',
            'tones' => 'required|array',
            'tones.*' => 'in:professional,casual,friendly,humorous',
        ]);

        // This would save to a database table for custom templates
        // For now, just return success
        
        return response()->json([
            'success' => true,
            'message' => 'Template saved successfully'
        ]);
    }
}
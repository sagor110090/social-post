<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class MediaController extends Controller
{
    /**
     * Upload image for social media post.
     */
    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:10240', // 10MB max
            'platform' => 'nullable|string|in:facebook,instagram,linkedin,twitter',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $file = $request->file('image');
            $platform = $request->get('platform', 'general');

            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = "media/{$user->id}/{$platform}/" . date('Y/m') . "/{$filename}";

            // Store original file
            $storedPath = $file->storeAs(
                dirname($path),
                basename($path),
                'public'
            );

            if (!$storedPath) {
                return response()->json(['error' => 'Failed to store file'], 500);
            }

            // Create different sizes for different platforms
            $sizes = $this->getOptimalSizes($platform);
            $processedImages = [];

            foreach ($sizes as $sizeName => $dimensions) {
                $image = Image::make($file);
                
                // Resize while maintaining aspect ratio
                if ($dimensions['width'] && $dimensions['height']) {
                    $image->fit($dimensions['width'], $dimensions['height'], function ($constraint) {
                        $constraint->upsize();
                    });
                } elseif ($dimensions['width']) {
                    $image->resize($dimensions['width'], null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                } elseif ($dimensions['height']) {
                    $image->resize(null, $dimensions['height'], function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }

                // Optimize for web
                $image->encode($file->getClientOriginalExtension(), 85);

                $sizePath = str_replace(".{$file->getClientOriginalExtension()}", "_{$sizeName}.{$file->getClientOriginalExtension()}", $storedPath);
                Storage::disk('public')->put($sizePath, $image->getEncoded());

                $processedImages[$sizeName] = Storage::url($sizePath);
            }

            // Get image metadata
            $imageInfo = getimagesize($file);
            $metadata = [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'width' => $imageInfo[0] ?? null,
                'height' => $imageInfo[1] ?? null,
                'platform' => $platform,
                'user_id' => $user->id,
            ];

            // Store media record in database (optional - for tracking)
            $mediaRecord = $user->media()->create([
                'filename' => $filename,
                'path' => $storedPath,
                'url' => Storage::url($storedPath),
                'mime_type' => $metadata['mime_type'],
                'size' => $metadata['size'],
                'metadata' => $metadata,
                'processed_images' => $processedImages,
            ]);

            return response()->json([
                'success' => true,
                'media_id' => $mediaRecord->id,
                'url' => Storage::url($storedPath),
                'processed_images' => $processedImages,
                'metadata' => $metadata,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload video for social media post.
     */
    public function uploadVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video' => 'required|file|mimes:mp4,mov,avi,webp|max:51200', // 50MB max
            'platform' => 'nullable|string|in:facebook,instagram,linkedin,twitter',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $file = $request->file('video');
            $platform = $request->get('platform', 'general');

            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = "media/{$user->id}/{$platform}/" . date('Y/m') . "/{$filename}";

            // Store file
            $storedPath = $file->storeAs(
                dirname($path),
                basename($path),
                'public'
            );

            if (!$storedPath) {
                return response()->json(['error' => 'Failed to store file'], 500);
            }

            // Get video metadata
            $metadata = [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'platform' => $platform,
                'user_id' => $user->id,
            ];

            // Store media record
            $mediaRecord = $user->media()->create([
                'filename' => $filename,
                'path' => $storedPath,
                'url' => Storage::url($storedPath),
                'mime_type' => $metadata['mime_type'],
                'size' => $metadata['size'],
                'metadata' => $metadata,
            ]);

            return response()->json([
                'success' => true,
                'media_id' => $mediaRecord->id,
                'url' => Storage::url($storedPath),
                'metadata' => $metadata,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to upload video: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get optimal image sizes for different platforms.
     */
    private function getOptimalSizes(string $platform): array
    {
        return match ($platform) {
            'facebook' => [
                'thumbnail' => ['width' => 160, 'height' => 160],
                'small' => ['width' => 400, 'height' => 400],
                'medium' => ['width' => 800, 'height' => 600],
                'large' => ['width' => 1200, 'height' => 630],
            ],
            'instagram' => [
                'thumbnail' => ['width' => 150, 'height' => 150],
                'square' => ['width' => 1080, 'height' => 1080],
                'story' => ['width' => 1080, 'height' => 1920],
            ],
            'linkedin' => [
                'thumbnail' => ['width' => 160, 'height' => 160],
                'small' => ['width' => 400, 'height' => 400],
                'large' => ['width' => 1200, 'height' => 627],
            ],
            'twitter' => [
                'thumbnail' => ['width' => 150, 'height' => 150],
                'small' => ['width' => 400, 'height' => 400],
                'medium' => ['width' => 800, 'height' => 450],
                'large' => ['width' => 1200, 'height' => 675],
            ],
            default => [
                'thumbnail' => ['width' => 150, 'height' => 150],
                'medium' => ['width' => 800, 'height' => 600],
                'large' => ['width' => 1200, 'height' => 800],
            ],
        };
    }

    /**
     * Delete uploaded media.
     */
    public function deleteMedia(Request $request, $mediaId)
    {
        try {
            $user = Auth::user();
            $media = $user->media()->findOrFail($mediaId);

            // Delete main file
            if (Storage::disk('public')->exists($media->path)) {
                Storage::disk('public')->delete($media->path);
            }

            // Delete processed images
            if ($media->processed_images) {
                foreach ($media->processed_images as $url) {
                    $path = str_replace('/storage/', '', $url);
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }

            // Delete database record
            $media->delete();

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete media: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's media library.
     */
    public function getMediaLibrary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|in:image,video',
            'platform' => 'nullable|string|in:facebook,instagram,linkedin,twitter',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $query = $user->media()->orderBy('created_at', 'desc');

            // Filter by type
            if ($request->get('type')) {
                $type = $request->get('type');
                $query->where('mime_type', 'like', $type . '/%');
            }

            // Filter by platform
            if ($request->get('platform')) {
                $query->whereJsonContains('metadata->platform', $request->get('platform'));
            }

            // Pagination
            $limit = $request->get('limit', 20);
            $offset = $request->get('offset', 0);

            $mediaItems = $query->offset($offset)->limit($limit)->get()->map(function ($media) {
                return [
                    'id' => $media->id,
                    'url' => $media->url,
                    'processed_images' => $media->processed_images,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'metadata' => $media->metadata,
                    'created_at' => $media->created_at->toISOString(),
                ];
            });

            $total = $query->count();

            return response()->json([
                'media' => $mediaItems,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => $offset + $limit < $total,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch media library: ' . $e->getMessage()
            ], 500);
        }
    }
}
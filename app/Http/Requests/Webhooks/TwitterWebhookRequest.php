<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class TwitterWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Handle webhook verification challenge
        if ($this->isVerificationRequest()) {
            return [
                'crc_token' => 'required|string',
            ];
        }

        // Handle webhook events
        return [
            'for_user_id' => 'required|string',
            'tweet_create_events' => 'nullable|array',
            'tweet_delete_events' => 'nullable|array',
            'favorite_events' => 'nullable|array',
            'follow_events' => 'nullable|array',
            'tweet_retweet_events' => 'nullable|array',
            'quote_tweet_events' => 'nullable|array',
            'direct_message_events' => 'nullable|array',
            'user_update_events' => 'nullable|array',
            'list_events' => 'nullable|array',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'for_user_id.required' => 'The for_user_id field is required.',
            'crc_token.required' => 'The crc_token is required for webhook verification.',
        ];
    }

    /**
     * Get the proper failed validation response for the request.
     */
    public function response(array $errors): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Twitter webhook validation failed',
            'errors' => $errors,
        ], 422);
    }

    /**
     * Check if this is a verification request.
     */
    protected function isVerificationRequest(): bool
    {
        return $this->has('crc_token');
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->isVerificationRequest()) {
                $this->validateEventStructure($validator);
            }
        });
    }

    /**
     * Validate the event structure.
     */
    protected function validateEventStructure($validator): void
    {
        // Validate that at least one event type is present
        $eventTypes = [
            'tweet_create_events',
            'tweet_delete_events',
            'favorite_events',
            'follow_events',
            'tweet_retweet_events',
            'quote_tweet_events',
            'direct_message_events',
            'user_update_events',
            'list_events',
        ];

        $hasEvents = false;
        foreach ($eventTypes as $eventType) {
            if ($this->has($eventType) && !empty($this->input($eventType))) {
                $hasEvents = true;
                $this->validateSpecificEvent($eventType, $validator);
                break;
            }
        }

        if (!$hasEvents) {
            $validator->errors()->add('events', 'At least one event type must be present.');
        }
    }

    /**
     * Validate specific event types.
     */
    protected function validateSpecificEvent(string $eventType, $validator): void
    {
        $events = $this->input($eventType, []);

        foreach ($events as $index => $event) {
            switch ($eventType) {
                case 'tweet_create_events':
                    $this->validateTweetCreateEvent($event, "{$eventType}.{$index}", $validator);
                    break;

                case 'tweet_delete_events':
                    $this->validateTweetDeleteEvent($event, "{$eventType}.{$index}", $validator);
                    break;

                case 'favorite_events':
                    $this->validateFavoriteEvent($event, "{$eventType}.{$index}", $validator);
                    break;

                case 'follow_events':
                    $this->validateFollowEvent($event, "{$eventType}.{$index}", $validator);
                    break;

                case 'tweet_retweet_events':
                    $this->validateRetweetEvent($event, "{$eventType}.{$index}", $validator);
                    break;

                case 'quote_tweet_events':
                    $this->validateQuoteTweetEvent($event, "{$eventType}.{$index}", $validator);
                    break;

                case 'direct_message_events':
                    $this->validateDirectMessageEvent($event, "{$eventType}.{$index}", $validator);
                    break;

                case 'user_update_events':
                    $this->validateUserUpdateEvent($event, "{$eventType}.{$index}", $validator);
                    break;

                case 'list_events':
                    $this->validateListEvent($event, "{$eventType}.{$index}", $validator);
                    break;
            }
        }
    }

    /**
     * Validate tweet create event.
     */
    protected function validateTweetCreateEvent(array $event, string $prefix, $validator): void
    {
        if (!isset($event['id_str'])) {
            $validator->errors()->add("{$prefix}.id_str", 'Tweet create events must include id_str.');
        }

        if (!isset($event['user'])) {
            $validator->errors()->add("{$prefix}.user", 'Tweet create events must include user data.');
        }

        if (!isset($event['created_at'])) {
            $validator->errors()->add("{$prefix}.created_at", 'Tweet create events must include created_at.');
        }
    }

    /**
     * Validate tweet delete event.
     */
    protected function validateTweetDeleteEvent(array $event, string $prefix, $validator): void
    {
        if (!isset($event['tweet']['id_str'])) {
            $validator->errors()->add("{$prefix}.tweet.id_str", 'Tweet delete events must include tweet.id_str.');
        }
    }

    /**
     * Validate favorite event.
     */
    protected function validateFavoriteEvent(array $event, string $prefix, $validator): void
    {
        if (!isset($event['event'])) {
            $validator->errors()->add("{$prefix}.event", 'Favorite events must include event type.');
        }

        if (!isset($event['created_timestamp'])) {
            $validator->errors()->add("{$prefix}.created_timestamp", 'Favorite events must include created_timestamp.');
        }

        if (!isset($event['favorited_tweet'])) {
            $validator->errors()->add("{$prefix}.favorited_tweet", 'Favorite events must include favorited_tweet data.');
        }
    }

    /**
     * Validate follow event.
     */
    protected function validateFollowEvent(array $event, string $prefix, $validator): void
    {
        if (!isset($event['event'])) {
            $validator->errors()->add("{$prefix}.event", 'Follow events must include event type.');
        }

        if (!isset($event['source'])) {
            $validator->errors()->add("{$prefix}.source", 'Follow events must include source data.');
        }

        if (!isset($event['target'])) {
            $validator->errors()->add("{$prefix}.target", 'Follow events must include target data.');
        }
    }

    /**
     * Validate retweet event.
     */
    protected function validateRetweetEvent(array $event, string $prefix, $validator): void
    {
        if (!isset($event['created_timestamp'])) {
            $validator->errors()->add("{$prefix}.created_timestamp", 'Retweet events must include created_timestamp.');
        }

        if (!isset($event['retweeted_tweet'])) {
            $validator->errors()->add("{$prefix}.retweeted_tweet", 'Retweet events must include retweeted_tweet data.');
        }
    }

    /**
     * Validate quote tweet event.
     */
    protected function validateQuoteTweetEvent(array $event, string $prefix, $validator): void
    {
        if (!isset($event['created_timestamp'])) {
            $validator->errors()->add("{$prefix}.created_timestamp", 'Quote tweet events must include created_timestamp.');
        }

        if (!isset($event['quoted_tweet'])) {
            $validator->errors()->add("{$prefix}.quoted_tweet", 'Quote tweet events must include quoted_tweet data.');
        }
    }

    /**
     * Validate direct message event.
     */
    protected function validateDirectMessageEvent(array $event, string $prefix, $validator): void
    {
        if (!isset($event['id'])) {
            $validator->errors()->add("{$prefix}.id", 'Direct message events must include id.');
        }

        if (!isset($event['message_create'])) {
            $validator->errors()->add("{$prefix}.message_create", 'Direct message events must include message_create data.');
        }
    }

    /**
     * Validate user update event.
     */
    protected function validateUserUpdateEvent(array $event, string $prefix, $validator): void
    {
        if (!isset($event['id_str'])) {
            $validator->errors()->add("{$prefix}.id_str", 'User update events must include id_str.');
        }

        if (!isset($event['created_timestamp'])) {
            $validator->errors()->add("{$prefix}.created_timestamp", 'User update events must include created_timestamp.');
        }
    }

    /**
     * Validate list event.
     */
    protected function validateListEvent(array $event, string $prefix, $validator): void
    {
        if (!isset($event['event'])) {
            $validator->errors()->add("{$prefix}.event", 'List events must include event type.');
        }

        if (!isset($event['created_timestamp'])) {
            $validator->errors()->add("{$prefix}.created_timestamp", 'List events must include created_timestamp.');
        }

        if (!isset($event['target'])) {
            $validator->errors()->add("{$prefix}.target", 'List events must include target data.');
        }
    }
}
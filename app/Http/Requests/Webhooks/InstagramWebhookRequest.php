<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class InstagramWebhookRequest extends FormRequest
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
                'hub_mode' => 'required|string|in:subscribe',
                'hub_challenge' => 'required|string',
                'hub_verify_token' => 'required|string',
            ];
        }

        // Handle webhook events
        return [
            'object' => 'required|string|in:instagram',
            'entry' => 'required|array|min:1',
            'entry.*.id' => 'required|string',
            'entry.*.time' => 'required|integer',
            'entry.*.changes' => 'nullable|array',
            'entry.*.messaging' => 'nullable|array',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'object.required' => 'The object field is required.',
            'object.in' => 'The object must be instagram.',
            'entry.required' => 'The entry array is required.',
            'entry.*.id.required' => 'Each entry must have an ID.',
            'entry.*.time.required' => 'Each entry must have a timestamp.',
            'hub_mode.in' => 'The hub mode must be subscribe.',
        ];
    }

    /**
     * Get the proper failed validation response for the request.
     */
    public function response(array $errors): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Instagram webhook validation failed',
            'errors' => $errors,
        ], 422);
    }

    /**
     * Check if this is a verification request.
     */
    protected function isVerificationRequest(): bool
    {
        return $this->has(['hub_mode', 'hub_challenge', 'hub_verify_token']);
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
        $entries = $this->input('entry', []);

        foreach ($entries as $index => $entry) {
            // Validate that at least one of the expected fields is present
            if (!isset($entry['changes']) && !isset($entry['messaging'])) {
                $validator->errors()->add("entry.{$index}", 'Each entry must contain changes or messaging data.');
            }

            // Validate changes structure if present
            if (isset($entry['changes'])) {
                foreach ($entry['changes'] as $changeIndex => $change) {
                    if (!isset($change['field'])) {
                        $validator->errors()->add("entry.{$index}.changes.{$changeIndex}", 'Each change must have a field.');
                    }

                    // Validate specific Instagram fields
                    $this->validateInstagramChange($change, "entry.{$index}.changes.{$changeIndex}", $validator);
                }
            }

            // Validate messaging structure if present
            if (isset($entry['messaging'])) {
                foreach ($entry['messaging'] as $messagingIndex => $messaging) {
                    if (!isset($messaging['sender'])) {
                        $validator->errors()->add("entry.{$index}.messaging.{$messagingIndex}", 'Each messaging event must have a sender.');
                    }
                }
            }
        }
    }

    /**
     * Validate Instagram-specific change fields.
     */
    protected function validateInstagramChange(array $change, string $prefix, $validator): void
    {
        $field = $change['field'] ?? '';
        $value = $change['value'] ?? [];

        switch ($field) {
            case 'media':
                if (!isset($value['media_id'])) {
                    $validator->errors()->add("{$prefix}.value", 'Media changes must include media_id.');
                }
                break;

            case 'comments':
                if (!isset($value['comment_id'])) {
                    $validator->errors()->add("{$prefix}.value", 'Comment changes must include comment_id.');
                }
                break;

            case 'mentions':
                if (!isset($value['media_id'])) {
                    $validator->errors()->add("{$prefix}.value", 'Mention changes must include media_id.');
                }
                break;

            case 'story_insights':
                if (!isset($value['story_id'])) {
                    $validator->errors()->add("{$prefix}.value", 'Story insights must include story_id.');
                }
                break;

            case 'user_insights':
                if (!isset($value['user_id'])) {
                    $validator->errors()->add("{$prefix}.value", 'User insights must include user_id.');
                }
                break;

            case 'business_account':
                if (!isset($value['id'])) {
                    $validator->errors()->add("{$prefix}.value", 'Business account updates must include id.');
                }
                break;
        }
    }
}
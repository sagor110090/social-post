<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class LinkedInWebhookRequest extends FormRequest
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
                'challenge_code' => 'required|string',
            ];
        }

        // Handle webhook events
        return [
            'event' => 'nullable|array',
            'shareUpdate' => 'nullable|array',
            'commentUpdate' => 'nullable|array',
            'reactionUpdate' => 'nullable|array',
            'personUpdate' => 'nullable|array',
            'organizationUpdate' => 'nullable|array',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'challenge_code.required' => 'The challenge_code is required for webhook verification.',
        ];
    }

    /**
     * Get the proper failed validation response for the request.
     */
    public function response(array $errors): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'LinkedIn webhook validation failed',
            'errors' => $errors,
        ], 422);
    }

    /**
     * Check if this is a verification request.
     */
    protected function isVerificationRequest(): bool
    {
        return $this->has('challenge_code');
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
            'event',
            'shareUpdate',
            'commentUpdate',
            'reactionUpdate',
            'personUpdate',
            'organizationUpdate',
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
        $event = $this->input($eventType);

        switch ($eventType) {
            case 'event':
                $this->validateStandardEvent($event, $validator);
                break;

            case 'shareUpdate':
                $this->validateShareUpdate($event, $validator);
                break;

            case 'commentUpdate':
                $this->validateCommentUpdate($event, $validator);
                break;

            case 'reactionUpdate':
                $this->validateReactionUpdate($event, $validator);
                break;

            case 'personUpdate':
                $this->validatePersonUpdate($event, $validator);
                break;

            case 'organizationUpdate':
                $this->validateOrganizationUpdate($event, $validator);
                break;
        }
    }

    /**
     * Validate standard event.
     */
    protected function validateStandardEvent(array $event, $validator): void
    {
        if (!isset($event['eventType'])) {
            $validator->errors()->add('event.eventType', 'Standard events must include eventType.');
        }

        if (!isset($event['object'])) {
            $validator->errors()->add('event.object', 'Standard events must include object type.');
        }

        if (!isset($event['objectId'])) {
            $validator->errors()->add('event.objectId', 'Standard events must include objectId.');
        }
    }

    /**
     * Validate share update event.
     */
    protected function validateShareUpdate(array $event, $validator): void
    {
        if (!isset($event['updateType'])) {
            $validator->errors()->add('shareUpdate.updateType', 'Share updates must include updateType.');
        }

        if (!isset($event['shareId'])) {
            $validator->errors()->add('shareUpdate.shareId', 'Share updates must include shareId.');
        }

        if (!isset($event['updateKey'])) {
            $validator->errors()->add('shareUpdate.updateKey', 'Share updates must include updateKey.');
        }

        // Validate specific update types
        $this->validateShareUpdateType($event, $validator);
    }

    /**
     * Validate share update type specific fields.
     */
    protected function validateShareUpdateType(array $event, $validator): void
    {
        $updateType = $event['updateType'] ?? '';

        switch ($updateType) {
            case 'CREATED':
            case 'UPDATED':
                if (!isset($event['owner'])) {
                    $validator->errors()->add('shareUpdate.owner', 'Share creation/update must include owner.');
                }
                break;

            case 'RESHARED':
                if (!isset($event['resharer'])) {
                    $validator->errors()->add('shareUpdate.resharer', 'Share reshares must include resharer.');
                }
                break;
        }
    }

    /**
     * Validate comment update event.
     */
    protected function validateCommentUpdate(array $event, $validator): void
    {
        if (!isset($event['updateType'])) {
            $validator->errors()->add('commentUpdate.updateType', 'Comment updates must include updateType.');
        }

        if (!isset($event['commentId'])) {
            $validator->errors()->add('commentUpdate.commentId', 'Comment updates must include commentId.');
        }

        if (!isset($event['updateKey'])) {
            $validator->errors()->add('commentUpdate.updateKey', 'Comment updates must include updateKey.');
        }

        // Validate specific update types
        $this->validateCommentUpdateType($event, $validator);
    }

    /**
     * Validate comment update type specific fields.
     */
    protected function validateCommentUpdateType(array $event, $validator): void
    {
        $updateType = $event['updateType'] ?? '';

        switch ($updateType) {
            case 'CREATED':
            case 'UPDATED':
                if (!isset($event['actor'])) {
                    $validator->errors()->add('commentUpdate.actor', 'Comment creation/update must include actor.');
                }
                break;

            case 'HIDDEN':
            case 'UNHIDDEN':
                if (!isset($event['actor'])) {
                    $validator->errors()->add('commentUpdate.actor', 'Comment hide/unhide must include actor.');
                }
                break;
        }
    }

    /**
     * Validate reaction update event.
     */
    protected function validateReactionUpdate(array $event, $validator): void
    {
        if (!isset($event['updateType'])) {
            $validator->errors()->add('reactionUpdate.updateType', 'Reaction updates must include updateType.');
        }

        if (!isset($event['reactionType'])) {
            $validator->errors()->add('reactionUpdate.reactionType', 'Reaction updates must include reactionType.');
        }

        if (!isset($event['updateKey'])) {
            $validator->errors()->add('reactionUpdate.updateKey', 'Reaction updates must include updateKey.');
        }

        // Validate specific update types
        $this->validateReactionUpdateType($event, $validator);
    }

    /**
     * Validate reaction update type specific fields.
     */
    protected function validateReactionUpdateType(array $event, $validator): void
    {
        $updateType = $event['updateType'] ?? '';

        switch ($updateType) {
            case 'CREATED':
            case 'DELETED':
                if (!isset($event['actor'])) {
                    $validator->errors()->add('reactionUpdate.actor', 'Reaction creation/deletion must include actor.');
                }
                break;
        }
    }

    /**
     * Validate person update event.
     */
    protected function validatePersonUpdate(array $event, $validator): void
    {
        if (!isset($event['updateType'])) {
            $validator->errors()->add('personUpdate.updateType', 'Person updates must include updateType.');
        }

        if (!isset($event['personId'])) {
            $validator->errors()->add('personUpdate.personId', 'Person updates must include personId.');
        }

        if (!isset($event['updateKey'])) {
            $validator->errors()->add('personUpdate.updateKey', 'Person updates must include updateKey.');
        }
    }

    /**
     * Validate organization update event.
     */
    protected function validateOrganizationUpdate(array $event, $validator): void
    {
        if (!isset($event['updateType'])) {
            $validator->errors()->add('organizationUpdate.updateType', 'Organization updates must include updateType.');
        }

        if (!isset($event['organizationId'])) {
            $validator->errors()->add('organizationUpdate.organizationId', 'Organization updates must include organizationId.');
        }

        if (!isset($event['updateKey'])) {
            $validator->errors()->add('organizationUpdate.updateKey', 'Organization updates must include updateKey.');
        }
    }
}
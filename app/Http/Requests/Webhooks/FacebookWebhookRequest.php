<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class FacebookWebhookRequest extends FormRequest
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
            'object' => 'required|string',
            'entry' => 'required|array|min:1',
            'entry.*.id' => 'required|string',
            'entry.*.time' => 'required|integer',
            'entry.*.changes' => 'nullable|array',
            'entry.*.messaging' => 'nullable|array',
            'entry.*.standby' => 'nullable|array',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'object.required' => 'The object field is required.',
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
            'message' => 'Facebook webhook validation failed',
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
            if (!isset($entry['changes']) && !isset($entry['messaging']) && !isset($entry['standby'])) {
                $validator->errors()->add("entry.{$index}", 'Each entry must contain changes, messaging, or standby data.');
            }

            // Validate changes structure if present
            if (isset($entry['changes'])) {
                foreach ($entry['changes'] as $changeIndex => $change) {
                    if (!isset($change['field'])) {
                        $validator->errors()->add("entry.{$index}.changes.{$changeIndex}", 'Each change must have a field.');
                    }
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
}
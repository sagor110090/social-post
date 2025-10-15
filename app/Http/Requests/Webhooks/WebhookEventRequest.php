<?php

namespace App\Http\Requests\Webhooks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

class WebhookEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Webhook requests are authorized through signature verification
        // This method always returns true as authorization is handled in controllers
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Base validation rules for webhook requests
            // Platform-specific validation is handled in individual controllers
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            //
        ];
    }

    /**
     * Get the proper failed validation response for the request.
     */
    public function response(array $errors): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $errors,
        ], 422);
    }
}
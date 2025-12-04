<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\ApiRequest;

/**
 * Login request validation.
 */
final class LoginRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'tenant_id.required' => 'Tenant ID is required.',
            'tenant_id.uuid' => 'Tenant ID must be a valid UUID.',
            'tenant_id.exists' => 'The specified tenant does not exist.',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * Base form request class for API requests.
 *
 * Provides:
 * - JSON validation error responses
 * - Tenant-aware validation
 * - Common validation rules
 * - Request sanitization
 */
abstract class ApiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Override in child classes for custom authorization.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validated data with trimmed strings.
     *
     * @param  array<int, string>|int|string|null  $keys
     *
     * @return array<string, mixed>
     */
    public function validated($keys = null, $default = null): array
    {
        /** @var array<string, mixed> $validated */
        $validated = parent::validated($keys, $default);

        return $this->trimStrings($validated);
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            new JsonResponse([
                'success' => false,
                'message' => 'Validation failed',
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $validator->errors()->toArray(),
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'request_id' => request()->header('X-Request-ID'),
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY),
        );
    }

    /**
     * Get the current authenticated user.
     */
    protected function authenticatedUser(): ?User
    {
        $user = $this->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Get the current tenant.
     */
    protected function currentTenant(): ?Tenant
    {
        return tenant();
    }

    /**
     * Get the current tenant ID.
     */
    protected function currentTenantId(): ?string
    {
        return tenant_id();
    }

    /**
     * Get common validation rules for tenant-scoped resources.
     *
     * @return array<string, mixed>
     */
    protected function tenantRules(): array
    {
        $tenantId = $this->currentTenantId();

        return [
            'tenant_id' => ['prohibited'], // Never allow tenant_id in request
        ];
    }

    /**
     * Common validation rules for pagination parameters.
     *
     * @return array<string, mixed>
     */
    protected function paginationRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'max:50'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * Common validation rules for date range filtering.
     *
     * @return array<string, mixed>
     */
    protected function dateRangeRules(string $startField = 'start_date', string $endField = 'end_date'): array
    {
        return [
            $startField => ['sometimes', 'date', 'before_or_equal:'.$endField],
            $endField => ['sometimes', 'date', 'after_or_equal:'.$startField],
        ];
    }

    /**
     * Common validation rules for search/filter parameters.
     *
     * @return array<string, mixed>
     */
    protected function searchRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'filter' => ['sometimes', 'array'],
            'filter.*' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * Recursively trim string values in an array.
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    private function trimStrings(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $result[$key] = trim($value);
            } elseif (is_array($value)) {
                /** @var array<string, mixed> $value */
                $result[$key] = $this->trimStrings($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}

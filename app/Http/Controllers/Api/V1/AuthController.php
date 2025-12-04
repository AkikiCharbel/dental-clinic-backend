<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Authentication endpoints for user login, logout, and session management.
 *
 * @tags Authentication
 */
final class AuthController extends Controller
{
    /**
     * Authenticate user and issue token
     *
     * Validates user credentials against the specified tenant and issues
     * a Sanctum API token for subsequent authenticated requests.
     *
     * @unauthenticated
     *
     * @response 200 scenario="success" {
     *   "success": true,
     *   "message": "Login successful",
     *   "data": {
     *     "user": {
     *       "id": "uuid",
     *       "first_name": "John",
     *       "last_name": "Doe",
     *       "email": "john@example.com",
     *       "primary_role": "dentist"
     *     },
     *     "tenant": {
     *       "id": "uuid",
     *       "name": "Demo Clinic",
     *       "slug": "demo"
     *     },
     *     "token": "1|abc123..."
     *   },
     *   "meta": {"timestamp": "...", "request_id": "..."}
     * }
     * @response 422 scenario="invalid_credentials" {
     *   "success": false,
     *   "message": "Invalid credentials",
     *   "error_code": "INVALID_CREDENTIALS",
     *   "meta": {"timestamp": "...", "request_id": "..."}
     * }
     * @response 403 scenario="inactive_user" {
     *   "success": false,
     *   "message": "Your account has been deactivated",
     *   "error_code": "USER_INACTIVE",
     *   "meta": {"timestamp": "...", "request_id": "..."}
     * }
     * @response 403 scenario="inactive_tenant" {
     *   "success": false,
     *   "message": "This clinic account is inactive",
     *   "error_code": "TENANT_INACTIVE",
     *   "meta": {"timestamp": "...", "request_id": "..."}
     * }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        /** @var string $tenantId */
        $tenantId = $validated['tenant_id'];
        /** @var string $email */
        $email = $validated['email'];
        /** @var string $password */
        $password = $validated['password'];

        // Find the tenant first
        $tenant = Tenant::query()->where('id', $tenantId)->first();

        if ($tenant === null) {
            return ApiResponse::error(
                message: 'Invalid credentials',
                errorCode: 'INVALID_CREDENTIALS',
                status: 422,
            );
        }

        // Check if tenant is active
        if (! $tenant->isActive()) {
            return ApiResponse::error(
                message: 'This clinic account is inactive or subscription has expired',
                errorCode: 'TENANT_INACTIVE',
                status: 403,
            );
        }

        // Find user by email within the tenant (bypass global scope for login)
        $user = User::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->first();

        // Validate credentials - use generic message for security
        if ($user === null || ! Hash::check($password, $user->password)) {
            return ApiResponse::error(
                message: 'Invalid credentials',
                errorCode: 'INVALID_CREDENTIALS',
                status: 422,
            );
        }

        // Check if user is active
        if (! $user->is_active) {
            return ApiResponse::error(
                message: 'Your account has been deactivated',
                errorCode: 'USER_INACTIVE',
                status: 403,
            );
        }

        // Update last login info
        $user->updateLastLogin($request->ip());

        // Create API token with role-based abilities and expiration
        $tokenResult = $user->createToken(
            name: 'api-token',
            abilities: $this->getAbilitiesForUser($user),
            expiresAt: now()->addDays(7),
        );

        return ApiResponse::success(
            data: [
                'user' => $this->formatUser($user),
                'tenant' => $this->formatTenant($tenant),
                'token' => $tokenResult->plainTextToken,
                'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
            ],
            message: 'Login successful',
        );
    }

    /**
     * Logout and revoke current token
     *
     * Revokes the current access token, invalidating it for future requests.
     *
     * @authenticated
     *
     * @response 200 scenario="success" {
     *   "success": true,
     *   "message": "Successfully logged out",
     *   "data": null,
     *   "meta": {"timestamp": "...", "request_id": "..."}
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current access token
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(
            data: null,
            message: 'Successfully logged out',
        );
    }

    /**
     * Get current user profile
     *
     * Returns the authenticated user's profile information and their tenant details.
     *
     * @authenticated
     *
     * @response 200 scenario="success" {
     *   "success": true,
     *   "message": "User profile retrieved",
     *   "data": {
     *     "user": {
     *       "id": "uuid",
     *       "first_name": "John",
     *       "last_name": "Doe",
     *       "email": "john@example.com",
     *       "primary_role": "dentist",
     *       "phone": "+961...",
     *       "last_login_at": "..."
     *     },
     *     "tenant": {
     *       "id": "uuid",
     *       "name": "Demo Clinic",
     *       "slug": "demo",
     *       "settings": {...}
     *     }
     *   },
     *   "meta": {"timestamp": "...", "request_id": "..."}
     * }
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $user->tenant;

        return ApiResponse::success(
            data: [
                'user' => $this->formatUser($user, includeDetails: true),
                'tenant' => $tenant ? $this->formatTenant($tenant, includeSettings: true) : null,
            ],
            message: 'User profile retrieved',
        );
    }

    /**
     * Refresh the current token
     *
     * Revokes the current token and issues a new one.
     *
     * @authenticated
     *
     * @response 200 scenario="success" {
     *   "success": true,
     *   "message": "Token refreshed successfully",
     *   "data": {
     *     "token": "2|xyz789..."
     *   },
     *   "meta": {"timestamp": "...", "request_id": "..."}
     * }
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Revoke current token
        $user->currentAccessToken()?->delete();

        // Create new token with same abilities and fresh expiration
        $tokenResult = $user->createToken(
            name: 'api-token',
            abilities: $this->getAbilitiesForUser($user),
            expiresAt: now()->addDays(7),
        );

        return ApiResponse::success(
            data: [
                'token' => $tokenResult->plainTextToken,
                'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
            ],
            message: 'Token refreshed successfully',
        );
    }

    /**
     * Format user data for response.
     *
     * @return array<string, mixed>
     */
    private function formatUser(User $user, bool $includeDetails = false): array
    {
        $data = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'primary_role' => $user->primary_role->value,
            'is_admin' => $user->isAdmin(),
            'is_provider' => $user->isProvider(),
        ];

        if ($includeDetails) {
            $data = array_merge($data, [
                'title' => $user->title,
                'phone' => $user->phone,
                'license_number' => $user->license_number,
                'specialization' => $user->specialization,
                'preferences' => $user->preferences,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]);
        }

        return $data;
    }

    /**
     * Format tenant data for response.
     *
     * @return array<string, mixed>
     */
    private function formatTenant(Tenant $tenant, bool $includeSettings = false): array
    {
        $data = [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'subscription_status' => $tenant->subscription_status->value,
            'subscription_plan' => $tenant->subscription_plan->value,
            'is_on_trial' => $tenant->isOnTrial(),
            'trial_days_remaining' => $tenant->trialDaysRemaining(),
        ];

        if ($includeSettings) {
            $data = array_merge($data, [
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'default_currency' => $tenant->default_currency,
                'timezone' => $tenant->timezone,
                'locale' => $tenant->locale,
                'settings' => $tenant->settings,
                'features' => $tenant->features,
            ]);
        }

        return $data;
    }

    /**
     * Get token abilities based on user role.
     *
     * @return array<int, string>
     */
    private function getAbilitiesForUser(User $user): array
    {
        // Admin gets full access
        if ($user->isAdmin()) {
            return ['*'];
        }

        // Providers (dentists, hygienists) get read, write, and delete-own
        if ($user->isProvider()) {
            return ['read', 'write', 'delete-own'];
        }

        // Other roles (receptionist, assistant) get read and write
        return ['read', 'write'];
    }
}

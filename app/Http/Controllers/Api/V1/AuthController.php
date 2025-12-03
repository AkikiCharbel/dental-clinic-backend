<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\UnauthorizedActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AuthController extends Controller
{
    /**
     * Handle user login.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // First check if tenant is active
        $tenant = Tenant::query()
            ->where('id', $validated['tenant_id'])
            ->first();

        if (! $tenant || ! $tenant->is_active) {
            throw new UnauthorizedActionException(
                message: 'Invalid credentials.',
                errorCode: 'AUTH_INVALID_CREDENTIALS',
            );
        }

        // Check if tenant subscription is accessible
        if (! $tenant->isAccessible()) {
            throw new UnauthorizedActionException(
                message: 'This clinic account is not currently active. Please contact support.',
                errorCode: 'AUTH_TENANT_INACTIVE',
            );
        }

        // Find user with matching email AND tenant_id (important for multi-tenancy)
        $user = User::withoutTenantScope()
            ->where('email', $validated['email'])
            ->where('tenant_id', $validated['tenant_id'])
            ->first();

        // Verify user exists and password matches
        // Use the same error message to prevent user enumeration attacks
        $password = $validated['password'];
        assert(is_string($password));

        if (! $user instanceof User || ! Hash::check($password, $user->password)) {
            throw new UnauthorizedActionException(
                message: 'Invalid credentials.',
                errorCode: 'AUTH_INVALID_CREDENTIALS',
            );
        }

        // Check if user is active
        if (! $user->is_active) {
            throw new UnauthorizedActionException(
                message: 'Your account has been deactivated. Please contact your administrator.',
                errorCode: 'AUTH_USER_INACTIVE',
            );
        }

        // Update last login information
        $user->updateLastLogin($request->ip());

        // Revoke any existing tokens for this user (optional: single device login)
        // $user->tokens()->delete();

        // Create a new Sanctum token
        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success(
            data: [
                'user' => $this->formatUser($user),
                'tenant' => $this->formatTenant($tenant),
                'token' => $token,
            ],
            message: 'Login successful',
        );
    }

    /**
     * Handle user logout.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Revoke the current token
        $user->currentAccessToken()->delete();

        return ApiResponse::success(
            message: 'Logout successful',
        );
    }

    /**
     * Get the currently authenticated user.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $user->tenant;

        return ApiResponse::success(
            data: [
                'user' => $this->formatUser($user),
                'tenant' => $tenant ? $this->formatTenant($tenant) : null,
            ],
        );
    }

    /**
     * Format user data for response.
     *
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'title' => $user->title,
            'email' => $user->email,
            'phone' => $user->phone,
            'primary_role' => $user->primary_role->value,
            'primary_role_label' => $user->primary_role->label(),
            'license_number' => $user->license_number,
            'specialization' => $user->specialization,
            'is_active' => $user->is_active,
            'is_admin' => $user->isAdmin(),
            'is_provider' => $user->isProvider(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'preferences' => $user->preferences,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format tenant data for response.
     *
     * @return array<string, mixed>
     */
    private function formatTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'subscription_status' => $tenant->subscription_status->value,
            'subscription_plan' => $tenant->subscription_plan->value,
            'is_on_trial' => $tenant->isOnTrial(),
            'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            'default_currency' => $tenant->default_currency,
            'timezone' => $tenant->timezone,
            'locale' => $tenant->locale,
            'settings' => $tenant->settings,
        ];
    }
}

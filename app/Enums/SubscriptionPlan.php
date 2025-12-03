<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionPlan: string
{
    case Basic = 'basic';
    case Professional = 'professional';
    case Enterprise = 'enterprise';

    /**
     * Get a human-readable label for the plan.
     */
    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Basic',
            self::Professional => 'Professional',
            self::Enterprise => 'Enterprise',
        };
    }

    /**
     * Get default features for this plan.
     *
     * @return array<string, bool|int>
     */
    public function defaultFeatures(): array
    {
        return match ($this) {
            self::Basic => [
                'max_users' => 5,
                'max_patients' => 500,
                'appointments' => true,
                'invoicing' => true,
                'reports' => false,
                'api_access' => false,
            ],
            self::Professional => [
                'max_users' => 25,
                'max_patients' => 5000,
                'appointments' => true,
                'invoicing' => true,
                'reports' => true,
                'api_access' => true,
            ],
            self::Enterprise => [
                'max_users' => -1, // unlimited
                'max_patients' => -1, // unlimited
                'appointments' => true,
                'invoicing' => true,
                'reports' => true,
                'api_access' => true,
            ],
        };
    }
}

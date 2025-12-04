<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionPlan: string
{
    case Basic = 'basic';
    case Professional = 'professional';
    case Enterprise = 'enterprise';

    /**
     * Get human-readable label for the plan.
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
     * Get maximum users allowed for the plan.
     */
    public function maxUsers(): int
    {
        return match ($this) {
            self::Basic => 5,
            self::Professional => 25,
            self::Enterprise => PHP_INT_MAX,
        };
    }

    /**
     * Get maximum locations allowed for the plan.
     */
    public function maxLocations(): int
    {
        return match ($this) {
            self::Basic => 1,
            self::Professional => 5,
            self::Enterprise => PHP_INT_MAX,
        };
    }

    /**
     * Get all plans as options for select inputs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $plan) => $plan->label(), self::cases()),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum PatientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Deceased = 'deceased';

    /**
     * Get human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Deceased => 'Deceased',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'warning',
            self::Deceased => 'gray',
        };
    }

    /**
     * Get all statuses as options for select inputs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $status) => $status->label(), self::cases()),
        );
    }
}

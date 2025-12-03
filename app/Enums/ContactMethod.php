<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactMethod: string
{
    case Phone = 'phone';
    case Email = 'email';
    case Sms = 'sms';

    /**
     * Get human-readable label for the method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone Call',
            self::Email => 'Email',
            self::Sms => 'SMS/Text Message',
        };
    }

    /**
     * Get all methods as options for select inputs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $method) => $method->label(), self::cases()),
        );
    }
}

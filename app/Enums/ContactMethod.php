<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactMethod: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Sms = 'sms';
    case Mail = 'mail';

    /**
     * Get a human-readable label for the contact method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Phone => 'Phone Call',
            self::Sms => 'SMS/Text Message',
            self::Mail => 'Postal Mail',
        };
    }
}

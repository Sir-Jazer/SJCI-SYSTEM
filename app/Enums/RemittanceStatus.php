<?php

namespace App\Enums;

enum RemittanceStatus: string
{
    case Due = 'due';
    case Approved = 'approved';
    case Remitted = 'remitted';

    public function label(): string
    {
        return match ($this) {
            self::Due => 'Due',
            self::Approved => 'Approved',
            self::Remitted => 'Remitted',
        };
    }
}

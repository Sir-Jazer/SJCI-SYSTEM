<?php

namespace App\Enums;

enum UserRole: string
{
    case HeadPastor = 'head_pastor';
    case OutreachPastor = 'outreach_pastor';
    // Reserved for a later phase — members do not log in during v1.
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::HeadPastor => 'Head Pastor',
            self::OutreachPastor => 'Outreach Pastor',
            self::Member => 'Member',
        };
    }

    /** Roles permitted to log into the admin panel in v1. */
    public function canLogin(): bool
    {
        return in_array($this, [self::HeadPastor, self::OutreachPastor], true);
    }
}

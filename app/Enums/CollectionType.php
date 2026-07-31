<?php

namespace App\Enums;

enum CollectionType: string
{
    case Offering = 'offering';
    case Tithe = 'tithe';

    public function label(): string
    {
        return match ($this) {
            self::Offering => 'Offering',
            self::Tithe => 'Tithe',
        };
    }

    /**
     * Only offerings are split 10% (Main Church) / 90% (Outreach Infrastructure Fund).
     * Tithes are recorded for transparency only — never split or remitted.
     */
    public function isSplit(): bool
    {
        return $this === self::Offering;
    }
}

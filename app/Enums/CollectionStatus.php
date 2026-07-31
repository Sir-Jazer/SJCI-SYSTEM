<?php

namespace App\Enums;

enum CollectionStatus: string
{
    case Pending = 'pending';
    case Returned = 'returned';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending approval',
            self::Returned => 'Returned',
            self::Locked => 'Locked',
        };
    }

    /** A locked record is immutable — corrections happen via adjustment entries. */
    public function isLocked(): bool
    {
        return $this === self::Locked;
    }
}

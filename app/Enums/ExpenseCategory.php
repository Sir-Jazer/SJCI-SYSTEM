<?php

namespace App\Enums;

/**
 * How an outreach may use its Infrastructure Fund (the 90% of offerings),
 * per the church's official procedure.
 */
enum ExpenseCategory: string
{
    case Building = 'building';
    case Equipment = 'equipment';
    case Expansion = 'expansion';
    case Ministry = 'ministry';
    case Operations = 'operations';
    case Activity = 'activity';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Building => 'Church building',
            self::Equipment => 'Equipment',
            self::Expansion => 'Church expansion',
            self::Ministry => 'Ministry development',
            self::Operations => 'Outreach operations',
            self::Activity => 'Event / activity',
            self::Other => 'Other',
        };
    }

    /** {value => label} for select inputs and filters. */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
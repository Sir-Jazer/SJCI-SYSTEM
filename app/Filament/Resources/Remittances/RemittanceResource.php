<?php

namespace App\Filament\Resources\Remittances;

use App\Filament\Resources\Remittances\Pages\ListRemittances;
use App\Filament\Resources\Remittances\Tables\RemittancesTable;
use App\Models\Remittance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class RemittanceResource extends Resource
{
    protected static ?string $model = Remittance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'remittance';

    protected static ?string $pluralModelLabel = 'Tithes of Tithes';

    /** Remittances are computed, never hand-created. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Outreach pastors only see their own church's remittances (read-only). */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();
        if ($user && $user->isOutreachPastor()) {
            $query->where('church_id', $user->church_id);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return RemittancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRemittances::route('/'),
        ];
    }
}

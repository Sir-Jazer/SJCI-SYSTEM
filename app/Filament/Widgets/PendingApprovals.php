<?php

namespace App\Filament\Widgets;

use App\Enums\CollectionStatus;
use App\Enums\CollectionType;
use App\Filament\Resources\Collections\CollectionResource;
use App\Models\Collection;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PendingApprovals extends TableWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    /** Only the Head Pastor approves, so only they need this queue. */
    public static function canView(): bool
    {
        return Auth::user()?->isHeadPastor() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pending your approval')
            ->description('Weekly reports submitted by outreach churches, newest first.')
            ->query(fn (): Builder => Collection::query()
                ->where('status', CollectionStatus::Pending)
                ->with(['church', 'submitter'])
                ->latest())
            ->emptyStateHeading('All caught up')
            ->emptyStateDescription('There are no reports waiting for approval.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10])
            ->columns([
                TextColumn::make('church.name')
                    ->label('Church'),

                TextColumn::make('week_of')
                    ->label('Week of')
                    ->date('M j, Y'),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (CollectionType $state): string => $state->label())
                    ->color(fn (CollectionType $state): string => $state === CollectionType::Offering ? 'info' : 'gray')
                    ->description(fn (Collection $record): ?string => $record->adjusts_id ? "Correction of #{$record->adjusts_id}" : null),

                TextColumn::make('amount')
                    ->money('PHP')
                    ->alignEnd(),

                TextColumn::make('submitter.name')
                    ->label('Recorded by'),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since(),
            ])
            ->recordUrl(fn (): string => CollectionResource::getUrl('index'));
    }
}
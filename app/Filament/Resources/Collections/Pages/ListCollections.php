<?php

namespace App\Filament\Resources\Collections\Pages;

use App\Enums\CollectionType;
use App\Filament\Resources\Collections\CollectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCollections extends ListRecords
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** Split the list by type so offerings and tithes each get a clean table. */
    public function getTabs(): array
    {
        return [
            'offerings' => Tab::make('Offerings')
                ->icon('heroicon-m-gift')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', CollectionType::Offering))
                ->badge(CollectionResource::getEloquentQuery()->where('type', CollectionType::Offering)->count()),

            'tithes' => Tab::make('Tithes')
                ->icon('heroicon-m-hand-raised')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', CollectionType::Tithe))
                ->badge(CollectionResource::getEloquentQuery()->where('type', CollectionType::Tithe)->count()),

            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'offerings';
    }
}
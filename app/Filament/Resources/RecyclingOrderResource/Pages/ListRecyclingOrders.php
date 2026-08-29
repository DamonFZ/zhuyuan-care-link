<?php

namespace App\Filament\Resources\RecyclingOrderResource\Pages;

use App\Filament\Resources\RecyclingOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecyclingOrders extends ListRecords
{
    protected static string $resource = RecyclingOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

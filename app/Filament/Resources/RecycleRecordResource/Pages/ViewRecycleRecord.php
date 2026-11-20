<?php

namespace App\Filament\Resources\RecycleRecordResource\Pages;

use App\Filament\Resources\RecycleRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRecycleRecord extends ViewRecord
{
    protected static string $resource = RecycleRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

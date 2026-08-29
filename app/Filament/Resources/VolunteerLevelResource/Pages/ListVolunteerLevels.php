<?php

namespace App\Filament\Resources\VolunteerLevelResource\Pages;

use App\Filament\Resources\VolunteerLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVolunteerLevels extends ListRecords
{
    protected static string $resource = VolunteerLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

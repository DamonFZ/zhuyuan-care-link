<?php

namespace App\Filament\Resources\VolunteerActivityResource\Pages;

use App\Filament\Resources\VolunteerActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVolunteerActivities extends ListRecords
{
    protected static string $resource = VolunteerActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\VolunteerServiceTypeResource\Pages;

use App\Filament\Resources\VolunteerServiceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVolunteerServiceTypes extends ListRecords
{
    protected static string $resource = VolunteerServiceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

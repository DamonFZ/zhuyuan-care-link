<?php

namespace App\Filament\Resources\VolunteerAttendanceResource\Pages;

use App\Filament\Resources\VolunteerAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVolunteerAttendances extends ListRecords
{
    protected static string $resource = VolunteerAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

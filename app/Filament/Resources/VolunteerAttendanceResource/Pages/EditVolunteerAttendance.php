<?php

namespace App\Filament\Resources\VolunteerAttendanceResource\Pages;

use App\Filament\Resources\VolunteerAttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVolunteerAttendance extends EditRecord
{
    protected static string $resource = VolunteerAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

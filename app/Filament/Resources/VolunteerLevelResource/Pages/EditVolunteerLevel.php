<?php

namespace App\Filament\Resources\VolunteerLevelResource\Pages;

use App\Filament\Resources\VolunteerLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVolunteerLevel extends EditRecord
{
    protected static string $resource = VolunteerLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

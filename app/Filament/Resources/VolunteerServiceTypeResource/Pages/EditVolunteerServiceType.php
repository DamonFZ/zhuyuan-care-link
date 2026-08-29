<?php

namespace App\Filament\Resources\VolunteerServiceTypeResource\Pages;

use App\Filament\Resources\VolunteerServiceTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVolunteerServiceType extends EditRecord
{
    protected static string $resource = VolunteerServiceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\VolunteerActivityResource\Pages;

use App\Filament\Resources\VolunteerActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVolunteerActivity extends EditRecord
{
    protected static string $resource = VolunteerActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

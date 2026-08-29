<?php

namespace App\Filament\Resources\RecyclingOrderResource\Pages;

use App\Filament\Resources\RecyclingOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecyclingOrder extends EditRecord
{
    protected static string $resource = RecyclingOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

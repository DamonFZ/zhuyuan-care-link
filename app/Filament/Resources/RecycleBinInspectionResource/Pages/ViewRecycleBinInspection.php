<?php

namespace App\Filament\Resources\RecycleBinInspectionResource\Pages;

use App\Filament\Resources\RecycleBinInspectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRecycleBinInspection extends ViewRecord
{
    protected static string $resource = RecycleBinInspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading("确认删除该巡检记录？")
                ->modalDescription("删除后不可恢复，相关操作日志仍会保留。"),
        ];
    }
}

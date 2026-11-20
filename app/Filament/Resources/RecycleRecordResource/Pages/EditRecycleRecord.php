<?php

namespace App\Filament\Resources\RecycleRecordResource\Pages;

use App\Filament\Resources\RecycleRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecycleRecord extends EditRecord
{
    protected static string $resource = RecycleRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading("确认删除该回收记录？")
                ->modalDescription("删除后不可恢复，相关操作日志仍会保留。"),
        ];
    }
}

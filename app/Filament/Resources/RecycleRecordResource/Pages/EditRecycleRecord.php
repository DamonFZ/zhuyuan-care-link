<?php

namespace App\Filament\Resources\RecycleRecordResource\Pages;

use App\Filament\Resources\RecycleRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecycleRecord extends EditRecord
{
    protected static string $resource = RecycleRecordResource::class;

    /**
     * DatePicker 仅提交日期（Y-m-d），这里拼回原记录的时分秒，
     * 避免编辑其它字段（如备注）时把录入时间意外重置为 00:00:00。
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data["created_at"]) && $this->record->created_at) {
            $data["created_at"] = $data["created_at"] . " " . $this->record->created_at->format("H:i:s");
        }

        return $data;
    }

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

<?php

namespace App\Filament\Resources\RecycleRecordResource\Pages;

use App\Filament\Resources\RecycleRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecycleRecord extends CreateRecord
{
    protected static string $resource = RecycleRecordResource::class;

    /**
     * DatePicker 仅提交日期（Y-m-d），拼上当前时分秒，
     * 使“录入时间”更贴近真实录入时刻（而非被归零为 00:00:00）。
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data["created_at"])) {
            $data["created_at"] = $data["created_at"] . " " . now()->format("H:i:s");
        }

        return $data;
    }
}

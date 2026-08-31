<?php

namespace App\Filament\Resources\VolunteerRecordResource\Pages;

use App\Filament\Resources\VolunteerRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVolunteerRecord extends EditRecord
{
    protected static string $resource = VolunteerRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ❌ 已禁用物理删除（使用列表页的冲销/撤销 Revoke 机制代替）
        ];
    }
}

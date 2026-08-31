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
            // ❌ 已禁用物理删除（使用列表页的冲销/撤销 Revoke 机制代替）
        ];
    }
}

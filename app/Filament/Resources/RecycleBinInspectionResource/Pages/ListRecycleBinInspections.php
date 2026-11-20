<?php

namespace App\Filament\Resources\RecycleBinInspectionResource\Pages;

use App\Filament\Resources\RecycleBinInspectionResource;
use Filament\Resources\Pages\ListRecords;

class ListRecycleBinInspections extends ListRecords
{
    protected static string $resource = RecycleBinInspectionResource::class;

    protected function getHeaderActions(): array
    {
        // 巡检数据由一线员工端提交，后台不提供录入入口
        return [];
    }
}

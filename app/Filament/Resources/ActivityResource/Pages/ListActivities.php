<?php

namespace App\Filament\Resources\ActivityResource\Pages;

use App\Filament\Resources\ActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    // 审计红线：移除顶部 Create 按钮
    protected function getHeaderActions(): array
    {
        return [];
    }
}

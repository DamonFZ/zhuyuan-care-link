<?php

namespace App\Filament\Widgets;

use App\Models\RecyclingOrder;
use App\Models\User;
use App\Models\VolunteerRecord;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        // 1. 累计回收衣物（斤）——只统计已完成订单
        $totalWeight = (float) RecyclingOrder::query()
            ->where('status', 'completed')
            ->sum('weight');

        // 2. 志愿者数量（人）——用户关联了志愿者等级才计入
        $volunteerCount = User::query()
            ->whereNotNull('volunteer_level_id')
            ->count();

        // 3. 累计志愿服务（小时）——只统计已完成记录的基础时长
        $totalServiceHours = (float) VolunteerRecord::query()
            ->where('status', 'completed')
            ->sum('base_hours');

        return [
            Stat::make(
                label: '累计回收衣物',
                value: number_format($totalWeight, 2) . ' 斤'
            )
                ->description('社区环保贡献')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-default',
                ]),

            Stat::make(
                label: '注册志愿者',
                value: number_format($volunteerCount) . ' 人'
            )
                ->description('活跃居民骨干')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->extraAttributes([
                    'class' => 'cursor-default',
                ]),

            Stat::make(
                label: '累计志愿服务',
                value: number_format($totalServiceHours, 1) . ' 小时'
            )
                ->description('邻里互助暖人心')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'cursor-default',
                ]),
        ];
    }
}

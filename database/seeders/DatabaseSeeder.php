<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 志愿者等级（幂等 upsert，id 固定便于后续外键引用）
        DB::table("volunteer_levels")->upsert([
            [
                "id" => 1,
                "name" => "普通志愿者",
                "multiplier" => "1.00",
            ],
            [
                "id" => 2,
                "name" => "骨干志愿者",
                "multiplier" => "1.20",
            ],
            [
                "id" => 3,
                "name" => "网格队长",
                "multiplier" => "1.50",
            ],
        ], ["id"], ["name", "multiplier"]);

        // 系统配置：志愿加成系数（保留做兼容）
        DB::table("settings")->upsert([
            [
                "key" => "volunteer_multiplier_normal",
                "name" => "普通志愿者加成系数",
                "value" => "1.0",
            ],
            [
                "key" => "volunteer_multiplier_backbone",
                "name" => "骨干志愿者加成系数",
                "value" => "1.2",
            ],
            [
                "key" => "volunteer_multiplier_captain",
                "name" => "网格队长加成系数",
                "value" => "1.5",
            ],
        ], ["key"], ["name", "value"]);
    }
}

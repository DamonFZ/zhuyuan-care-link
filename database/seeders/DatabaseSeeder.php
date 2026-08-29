<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
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

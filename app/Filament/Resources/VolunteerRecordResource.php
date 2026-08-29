<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VolunteerRecordResource\Pages;
use App\Models\VolunteerRecord;
use App\Models\VolunteerServiceType;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VolunteerRecordResource extends Resource
{
    protected static ?string $model = VolunteerRecord::class;

    protected static ?string $navigationIcon = "heroicon-o-heart";

    protected static ?string $navigationLabel = "志愿服务";

    protected static ?string $modelLabel = "志愿记录";

    protected static ?string $pluralModelLabel = "志愿服务";

    /**
     * 统一的计算函数（前端侧）：
     * - final_hours = base_hours × multiplier
     * - reward_points = base_hours × base_reward_rate × multiplier
     */
    public static function recompute(Set $set, Get $get): void
    {
        $base = (float)($get("base_hours") ?? 0);
        $mult = (float)($get("multiplier") ?? 1.00);
        $rate = (float)($get("_rate") ?? ($get("volunteer_service_type_id")
            ? (VolunteerServiceType::find($get("volunteer_service_type_id"))?->base_reward_rate ?? 0)
            : 0));

        if ($base > 0 && $mult > 0) {
            $finalH = number_format(round($base * $mult, 1), 1, ".", "");
            $set("final_hours", $finalH);
        } else {
            $set("final_hours", null);
        }

        if ($base > 0 && $mult > 0 && $rate > 0) {
            $reward = number_format(round($base * $rate * $mult, 2), 2, ".", "");
            $set("reward_points", $reward);
        } else {
            $set("reward_points", "0.00");
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make("服务信息")
                    ->schema([
                        Forms\Components\Select::make("user_id")
                            ->label("用户")
                            ->relationship("user", "name")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                if (blank($state)) {
                                    $set("multiplier", "1.00");
                                    $set("final_hours", null);
                                    $set("reward_points", "0.00");
                                    return;
                                }
                                $user = User::with("volunteerLevel")->find($state);
                                $mult = (string)($user?->volunteerLevel?->multiplier ?? "1.00");
                                $set("multiplier", $mult);
                                static::recompute($set, $get);
                            }),
                        Forms\Components\Select::make("volunteer_service_type_id")
                            ->label("服务岗位类型")
                            ->relationship("volunteerServiceType", "name")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                if (filled($state)) {
                                    $type = VolunteerServiceType::find($state);
                                    // 把 rate 写入隐藏只读态，供 recompute 直接使用
                                    $set("_rate", (string)($type?->base_reward_rate ?? "0"));
                                }
                                static::recompute($set, $get);
                            }),
                        Forms\Components\TextInput::make("title")
                            ->label("服务项目")
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make("base_hours")
                            ->label("基础时长(小时)")
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                                if (blank($state)) {
                                    $set("final_hours", null);
                                    $set("reward_points", "0.00");
                                    return;
                                }
                                static::recompute($set, $get);
                            }),
                        Forms\Components\TextInput::make("multiplier")
                            ->label("加成系数（跟随用户等级）")
                            ->numeric()
                            ->inputMode("decimal")
                            ->disabled()
                            ->dehydrated(true)
                            ->suffix("x")
                            ->helperText("根据所选用户的志愿者等级自动带入，不可手动修改"),
                        Forms\Components\TextInput::make("final_hours")
                            ->label("最终时长(小时)")
                            ->numeric()
                            ->inputMode("decimal")
                            ->disabled()
                            ->dehydrated(true)
                            ->helperText("= 基础时长 × 加成系数；后端严格重算兜底入库"),
                        Forms\Components\TextInput::make("reward_points")
                            ->label("本次消费金奖励(元)")
                            ->numeric()
                            ->inputMode("decimal")
                            ->disabled()
                            ->dehydrated(true)
                            ->prefix("¥")
                            ->helperText("= 基础时长 × 岗位时薪 × 加成系数；后端严格重算兜底入库"),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make("id")
                    ->label("ID")
                    ->sortable(),
                Tables\Columns\TextColumn::make("user.name")
                    ->label("用户")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make("volunteerServiceType.name")
                    ->label("岗位")
                    ->badge()
                    ->placeholder("未设置"),
                Tables\Columns\TextColumn::make("title")
                    ->label("服务项目")
                    ->searchable(),
                Tables\Columns\TextColumn::make("base_hours")
                    ->label("基础时长")
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make("multiplier")
                    ->label("系数")
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state . "x")
                    ->color(fn ($state) => match ((string)$state) {
                        "1.5" => "warning",
                        "1.2" => "info",
                        default => "gray",
                    }),
                Tables\Columns\TextColumn::make("final_hours")
                    ->label("最终时长")
                    ->numeric(1)
                    ->sortable(),
                Tables\Columns\TextColumn::make("reward_points")
                    ->label("消费金奖励")
                    ->money("CNY")
                    ->color("success")
                    ->sortable(),
                Tables\Columns\TextColumn::make("created_at")
                    ->label("服务时间")
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make("multiplier")
                    ->label("加成系数")
                    ->options([
                        "1.0" => "普通 (1.0x)",
                        "1.2" => "骨干 (1.2x)",
                        "1.5" => "队长 (1.5x)",
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            "index" => Pages\ListVolunteerRecords::route("/"),
            "create" => Pages\CreateVolunteerRecord::route("/create"),
            "edit" => Pages\EditVolunteerRecord::route("/{record}/edit"),
        ];
    }
}

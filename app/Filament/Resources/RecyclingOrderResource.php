<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecyclingOrderResource\Pages;
use App\Models\RecyclingOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RecyclingOrderResource extends Resource
{
    protected static ?string $model = RecyclingOrder::class;

    protected static ?string $navigationIcon = "heroicon-o-arrow-path";

    protected static ?string $navigationLabel = "旧衣回收";

    protected static ?string $modelLabel = "回收订单";

    protected static ?string $pluralModelLabel = "旧衣回收";

    protected static ?string $navigationGroup = '业务台账';

    protected static ?int $navigationSort = 1;

    /**
     * 回收模块暂未上线，从左侧导航隐藏
     */
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make("预约信息")
                    ->schema([
                        Forms\Components\Select::make("user_id")
                            ->label("用户")
                            ->relationship("user", "name")
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make("estimated_weight")
                            ->label("预估重量")
                            ->placeholder("如 3~20kg")
                            ->maxLength(50),
                        Forms\Components\DateTimePicker::make("appointment_time")
                            ->label("预约上门时间"),
                        Forms\Components\FileUpload::make("images")
                            ->label("上传图片")
                            ->multiple()
                            ->image()
                            ->imageEditor()
                            ->directory("recycling")
                            ->storeFileNamesIn("images"),
                        Forms\Components\Textarea::make("remark")
                            ->label("备注")
                            ->rows(2)
                            ->maxLength(500),
                    ])->columns(2),

                Forms\Components\Section::make("回收结算")
                    ->schema([
                        Forms\Components\TextInput::make("actual_weight")
                            ->label("实际称重(斤)")
                            ->numeric()
                            ->inputMode("decimal")
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($set, $state, $get) {
                                if ($state && $get("reward_type")) {
                                    $set("reward_amount", round((float)$state * 0.4, 2));
                                    $set("weight", $state);
                                }
                            }),
                        Forms\Components\Select::make("reward_type")
                            ->label("兑换方式")
                            ->options([
                                "points" => "积分",
                                "cash" => "现金",
                            ])
                            ->default("points")
                            ->required()
                            ->afterStateUpdated(function ($set, $state, $get) {
                                if ($get("actual_weight")) {
                                    $set("reward_amount", round((float)$get("actual_weight") * 0.4, 2));
                                }
                            }),
                        Forms\Components\TextInput::make("reward_amount")
                            ->label("折算金额(元)")
                            ->numeric()
                            ->inputMode("decimal")
                            ->required()
                            ->helperText("按 0.4 元/斤 自动计算，可手动修改"),
                        Forms\Components\Select::make("status")
                            ->label("状态")
                            ->options([
                                "pending"    => "待处理",
                                "processing" => "处理中",
                                "completed"  => "已完成",
                                "cancelled"  => "已取消",
                            ])
                            ->default("pending")
                            ->required(),
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
                    ->label("用户姓名")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make("estimated_weight")
                    ->label("预估重量")
                    ->badge()
                    ->color("info")
                    ->placeholder("未填写"),
                Tables\Columns\TextColumn::make("appointment_time")
                    ->label("预约时间")
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make("actual_weight")
                    ->label("实际称重")
                    ->numeric()
                    ->suffix(" 斤")
                    ->placeholder("待称重"),
                Tables\Columns\TextColumn::make("reward_amount")
                    ->label("折算金额")
                    ->money("CNY")
                    ->sortable(),
                Tables\Columns\TextColumn::make("status")
                    ->label("状态")
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        "pending"    => "warning",
                        "processing" => "info",
                        "completed"  => "success",
                        "cancelled"  => "gray",
                        "revoked"    => "danger",
                        default      => "gray",
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        "pending"    => "待处理",
                        "processing" => "处理中",
                        "completed"  => "已完成",
                        "cancelled"  => "已取消",
                        "revoked"    => "已冲销",
                        default      => $state,
                    }),
                Tables\Columns\TextColumn::make("created_at")
                    ->label("创建时间")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make("status")
                    ->label("状态")
                    ->options([
                        "pending"    => "待处理",
                        "processing" => "处理中",
                        "completed"  => "已完成",
                        "cancelled"  => "已取消",
                        "revoked"    => "已冲销",
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('revoke')
                    ->label('冲销订单')
                    ->color('danger')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->modalHeading('确认冲销该旧衣回收订单？')
                    ->modalDescription('冲销后：积分型订单将自动扣回已发消费金并产生负向流水；现金型订单仅标记为 revoked。此操作不可逆！')
                    ->visible(fn ($record) => $record->status === 'completed')
                    ->action(function ($record): void {
                        \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                            // 1) 积分类型：扣回消费金
                            if ($record->reward_type === 'points'
                                && bccomp((string)($record->reward_amount ?? 0), '0.00', 2) > 0
                            ) {
                                $record->user->modifyPoints(
                                    bcmul((string)$record->reward_amount, '-1', 2),
                                    "撤销旧衣回收订单扣回消费金：{$record->weight}斤"
                                );
                            }

                            // 2) 标记订单为已冲销
                            $record->update(['status' => 'revoked']);
                        });
                    }),
            ])
            ->bulkActions([
                // ❌ 已禁用批量物理删除（使用冲销revoke机制代替）
            ]);
    }

    public static function getPages(): array
    {
        return [
            "index" => Pages\ListRecyclingOrders::route("/"),
            "create" => Pages\CreateRecyclingOrder::route("/create"),
            "edit" => Pages\EditRecyclingOrder::route("/{record}/edit"),
        ];
    }
}

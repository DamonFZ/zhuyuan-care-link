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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make("订单信息")
                    ->schema([
                        Forms\Components\Select::make("user_id")
                            ->label("用户")
                            ->relationship("user", "name")
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make("weight")
                            ->label("重量(斤)")
                            ->numeric()
                            ->inputMode("decimal")
                            ->required()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($set, $state, $get) {
                                if ($state && $get("reward_type")) {
                                    $set("reward_amount", round($state * 0.4, 2));
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
                                if ($get("weight")) {
                                    $set("reward_amount", round($get("weight") * 0.4, 2));
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
                                "pending" => "待处理",
                                "completed" => "已完成",
                                "cancelled" => "已取消",
                            ])
                            ->default("completed")
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
                    ->label("用户")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make("weight")
                    ->label("重量(斤)")
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make("reward_type")
                    ->label("兑换方式")
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === "points" ? "积分" : "现金")
                    ->color(fn ($state) => $state === "points" ? "info" : "success"),
                Tables\Columns\TextColumn::make("reward_amount")
                    ->label("折算金额")
                    ->money("CNY")
                    ->sortable(),
                Tables\Columns\TextColumn::make("status")
                    ->label("状态")
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        "pending"   => "warning",
                        "completed" => "success",
                        "cancelled" => "gray",
                        "revoked"   => "danger",
                        default     => "gray",
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        "pending"   => "待处理",
                        "completed" => "已完成",
                        "cancelled" => "已取消",
                        "revoked"   => "已冲销",
                        default     => $state,
                    }),
                Tables\Columns\TextColumn::make("created_at")
                    ->label("创建时间")
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make("reward_type")
                    ->label("兑换方式")
                    ->options(["points" => "积分", "cash" => "现金"]),
                Tables\Filters\SelectFilter::make("status")
                    ->label("状态")
                    ->options(["pending" => "待处理", "completed" => "已完成", "cancelled" => "已取消", "revoked" => "已冲销"]),
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

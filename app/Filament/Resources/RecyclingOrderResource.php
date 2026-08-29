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
                    ->color(fn ($state) => match ($state) {
                        "pending" => "warning",
                        "completed" => "success",
                        "cancelled" => "danger",
                        default => "gray",
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
                    ->options(["pending" => "待处理", "completed" => "已完成", "cancelled" => "已取消"]),
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
            "index" => Pages\ListRecyclingOrders::route("/"),
            "create" => Pages\CreateRecyclingOrder::route("/create"),
            "edit" => Pages\EditRecyclingOrder::route("/{record}/edit"),
        ];
    }
}

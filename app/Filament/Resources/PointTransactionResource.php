<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PointTransactionResource\Pages;
use App\Models\PointTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PointTransactionResource extends Resource
{
    protected static ?string $model = PointTransaction::class;

    protected static ?string $navigationIcon = "heroicon-o-currency-dollar";

    protected static ?string $navigationLabel = "消费金流水";

    protected static ?string $modelLabel = "消费金记录";

    protected static ?string $pluralModelLabel = "消费金流水";

    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        // 只读详情用
        return $form
            ->schema([
                Forms\Components\Section::make("基本信息")
                    ->schema([
                        Forms\Components\TextInput::make("id")->label("ID")->disabled(),
                        Forms\Components\TextInput::make("user.name")->label("用户")->disabled(),
                        Forms\Components\TextInput::make("event_name")->label("事件描述")->disabled(),
                        Forms\Components\TextInput::make("created_at")->label("创建时间")->disabled(),
                    ])->columns(2),
                Forms\Components\Section::make("积分变动")
                    ->schema([
                        Forms\Components\TextInput::make("original_points")->label("变动前余额")->disabled(),
                        Forms\Components\TextInput::make("change_points")->label("变动额")->disabled(),
                        Forms\Components\TextInput::make("new_points")->label("变动后余额")->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make("id")
                    ->label("ID")
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make("user.name")
                    ->label("用户姓名")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make("event_name")
                    ->label("事件描述")
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make("original_points")
                    ->label("变动前")
                    ->numeric(2)
                    ->alignRight(),
                Tables\Columns\TextColumn::make("change_points")
                    ->label("变动额")
                    ->badge()
                    ->numeric(2)
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => $state > 0 ? "+" . $state : $state)
                    ->color(fn ($state) => $state > 0 ? "success" : ($state < 0 ? "danger" : "gray")),
                Tables\Columns\TextColumn::make("new_points")
                    ->label("变动后")
                    ->numeric(2)
                    ->alignRight()
                    ->sortable(),
                Tables\Columns\TextColumn::make("created_at")
                    ->label("创建时间")
                    ->dateTime("Y-m-d H:i:s")
                    ->sortable(),
            ])
            ->defaultSort("created_at", "desc")
            ->filters([
                Tables\Filters\Filter::make("positive")
                    ->label("只看收入")
                    ->query(fn ($q) => $q->where("change_points", ">", 0)),
                Tables\Filters\Filter::make("negative")
                    ->label("只看支出")
                    ->query(fn ($q) => $q->where("change_points", "<", 0)),
                Tables\Filters\SelectFilter::make("user")
                    ->label("用户")
                    ->relationship("user", "name")
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label("查看"),
                // ❌ 禁用：CreateAction / EditAction / DeleteAction
            ])
            ->bulkActions([
                // ❌ 禁用：DeleteBulkAction
            ])
            ->headerActions([]); // ❌ 移除顶部 Create 按钮
    }

    public static function getPages(): array
    {
        return [
            "index" => Pages\ListPointTransactions::route("/"),
            "view"  => Pages\ViewPointTransaction::route("/{record}"),
        ];
    }

    // 审计红线：纯查账面板，禁止任何写操作
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
}

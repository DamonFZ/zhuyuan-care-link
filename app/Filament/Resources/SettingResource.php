<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = "heroicon-o-cog";

    protected static ?string $navigationLabel = "系统配置";

    protected static ?string $modelLabel = "配置项";

    protected static ?string $pluralModelLabel = "系统配置";

    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make("基础信息")
                    ->schema([
                        Forms\Components\TextInput::make("key")
                            ->label("配置键")
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText("系统内部标识符，建议使用 snake_case"),
                        Forms\Components\TextInput::make("name")
                            ->label("配置名称")
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make("value")
                            ->label("配置值")
                            ->required()
                            ->rows(3)
                            ->helperText("修改后立即生效，请注意影响范围"),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make("key")
                    ->label("配置键")
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make("name")
                    ->label("配置名称")
                    ->searchable(),
                Tables\Columns\TextColumn::make("value")
                    ->label("当前值")
                    ->limit(50),
                Tables\Columns\TextColumn::make("updated_at")
                    ->label("更新时间")
                    ->dateTime()
                    ->sortable(),
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
            "index" => Pages\ListSettings::route("/"),
            "create" => Pages\CreateSetting::route("/create"),
            "edit" => Pages\EditSetting::route("/{record}/edit"),
        ];
    }
}

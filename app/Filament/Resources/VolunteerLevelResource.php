<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VolunteerLevelResource\Pages;
use App\Models\VolunteerLevel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VolunteerLevelResource extends Resource
{
    protected static ?string $model = VolunteerLevel::class;

    protected static ?string $navigationIcon = "heroicon-o-academic-cap";

    protected static ?string $navigationLabel = "志愿者等级";

    protected static ?string $modelLabel = "等级";

    protected static ?string $pluralModelLabel = "志愿者等级";

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make("等级信息")
                    ->schema([
                        Forms\Components\TextInput::make("name")
                            ->label("等级名称")
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make("multiplier")
                            ->label("加成系数")
                            ->required()
                            ->numeric()
                            ->inputMode("decimal")
                            ->default("1.00")
                            ->step("0.01")
                            ->rules(["decimal:2", "min:0.01", "max:9.99"]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make("id")
                    ->label("ID")
                    ->sortable(),
                Tables\Columns\TextColumn::make("name")
                    ->label("等级名称")
                    ->badge()
                    ->searchable()
                    ->color(fn ($record) => match ($record->multiplier) {
                        "1.50" => "warning",
                        "1.20" => "info",
                        default => "gray",
                    }),
                Tables\Columns\TextColumn::make("multiplier")
                    ->label("加成系数")
                    ->numeric(2)
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state . "x"),
                Tables\Columns\TextColumn::make("users_count")
                    ->label("人数")
                    ->counts("users"),
                Tables\Columns\TextColumn::make("updated_at")
                    ->label("更新时间")
                    ->dateTime("Y-m-d H:i:s")
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
            "index"  => Pages\ListVolunteerLevels::route("/"),
            "create" => Pages\CreateVolunteerLevel::route("/create"),
            "edit"   => Pages\EditVolunteerLevel::route("/{record}/edit"),
        ];
    }
}

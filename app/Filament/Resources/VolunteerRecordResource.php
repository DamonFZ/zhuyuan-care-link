<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VolunteerRecordResource\Pages;
use App\Models\VolunteerRecord;
use Filament\Forms;
use Filament\Forms\Form;
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
                            ->required(),
                        Forms\Components\TextInput::make("title")
                            ->label("服务项目")
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make("base_hours")
                            ->label("基础时长(小时)")
                            ->numeric()
                            ->required()
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($set, $state, $get) {
                                if ($state !== null && $get("multiplier")) {
                                    $set("final_hours", round($state * $get("multiplier"), 1));
                                }
                            }),
                        Forms\Components\Select::make("multiplier")
                            ->label("加成系数")
                            ->options([
                                "1.0" => "普通 (1.0x)",
                                "1.2" => "骨干 (1.2x)",
                                "1.5" => "队长 (1.5x)",
                            ])
                            ->default("1.0")
                            ->required()
                            ->afterStateUpdated(function ($set, $state, $get) {
                                if ($get("base_hours") !== null) {
                                    $set("final_hours", round($get("base_hours") * (float)$state, 1));
                                }
                            }),
                        Forms\Components\TextInput::make("final_hours")
                            ->label("最终时长(小时)")
                            ->numeric()
                            ->inputMode("decimal")
                            ->helperText("按基础时长 x 加成系数自动计算，可手动修改"),
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

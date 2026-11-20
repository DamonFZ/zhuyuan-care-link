<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecycleRecordResource\Pages;
use App\Models\RecycleRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecycleRecordResource extends Resource
{
    protected static ?string $model = RecycleRecord::class;

    protected static ?string $navigationIcon = "heroicon-o-archive-box";

    protected static ?string $navigationLabel = "回收记录";

    protected static ?string $modelLabel = "回收记录";

    protected static ?string $pluralModelLabel = "回收记录";

    protected static ?string $navigationGroup = '业务台账';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make("回收信息")
                    ->schema([
                        Forms\Components\Select::make("user_id")
                            ->label("回收人员")
                            ->relationship("user", "name")
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make("weight")
                            ->label("实际重量")
                            ->numeric()
                            ->inputMode("decimal")
                            ->suffix("kg")
                            ->default(0)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make("现场照片")
                    ->schema([
                        Forms\Components\FileUpload::make("clothes_image")
                            ->label("衣物照片")
                            ->disk("public")
                            ->directory("recycle/clothes")
                            ->image()
                            ->imageEditor()
                            ->maxSize(5120)
                            ->columnSpan(1),
                        Forms\Components\FileUpload::make("scale_image")
                            ->label("秤重照片")
                            ->disk("public")
                            ->directory("recycle/scale")
                            ->image()
                            ->imageEditor()
                            ->maxSize(5120)
                            ->columnSpan(1),
                    ])->columns(2),
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
                    ->label("回收人员")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make("user.role")
                    ->label("角色")
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        "resident" => "社区居民",
                        "merchant" => "核销商户",
                        "operator" => "操作员",
                        "recycler" => "回收员",
                        default    => $state ?? "-",
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        "resident" => "success",
                        "merchant" => "warning",
                        "operator" => "danger",
                        "recycler" => "info",
                        default    => "gray",
                    }),
                Tables\Columns\ImageColumn::make("clothes_image")
                    ->label("衣物照片")
                    ->disk("public")
                    ->square()
                    ->width(80)
                    ->height(80)
                    ->defaultImageUrl("data:image/svg+xml;charset=UTF-8," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><rect width="80" height="80" fill="%23f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-size="12">无图</text></svg>'))
                    ->extraImgAttributes(["loading" => "lazy"]),
                Tables\Columns\ImageColumn::make("scale_image")
                    ->label("秤重照片")
                    ->disk("public")
                    ->square()
                    ->width(80)
                    ->height(80)
                    ->defaultImageUrl("data:image/svg+xml;charset=UTF-8," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><rect width="80" height="80" fill="%23f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-size="12">无图</text></svg>'))
                    ->extraImgAttributes(["loading" => "lazy"]),
                Tables\Columns\TextColumn::make("weight")
                    ->label("重量")
                    ->numeric(2)
                    ->suffix(" kg")
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label("合计(kg)")
                            ->numeric(2)
                    ),
                Tables\Columns\TextColumn::make("created_at")
                    ->label("录入时间")
                    ->dateTime("Y-m-d H:i:s")
                    ->sortable(),
            ])
            ->defaultSort("created_at", "desc")
            ->filters([
                // 按回收人员筛选
                Tables\Filters\SelectFilter::make("user")
                    ->label("回收人员")
                    ->relationship("user", "name")
                    ->searchable()
                    ->preload(),

                // 按录入日期范围筛选
                Tables\Filters\Filter::make("created_at")
                    ->label("录入日期")
                    ->form([
                        Forms\Components\DatePicker::make("created_from")
                            ->label("起"),
                        Forms\Components\DatePicker::make("created_until")
                            ->label("止"),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data["created_from"] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate("created_at", ">=", $date)
                            )
                            ->when(
                                $data["created_until"] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate("created_at", "<=", $date)
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label("查看"),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading("确认删除该回收记录？")
                    ->modalDescription("删除后不可恢复，相关操作日志仍会保留。"),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make("回收信息")
                    ->schema([
                        TextEntry::make("user.name")->label("回收人员"),
                        TextEntry::make("user.role")
                            ->label("角色")
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                "resident" => "社区居民",
                                "merchant" => "核销商户",
                                "operator" => "操作员",
                                "recycler" => "回收员",
                                default    => $state ?? "-",
                            })
                            ->color(fn (?string $state): string => match ($state) {
                                "resident" => "success",
                                "merchant" => "warning",
                                "operator" => "danger",
                                "recycler" => "info",
                                default    => "gray",
                            }),
                        TextEntry::make("weight")
                            ->label("实际重量")
                            ->numeric(2)
                            ->suffix(" kg"),
                        TextEntry::make("created_at")
                            ->label("录入时间")
                            ->dateTime("Y-m-d H:i:s"),
                    ])->columns(2),

                Section::make("现场照片")
                    ->schema([
                        ImageEntry::make("clothes_image")
                            ->label("衣物照片")
                            ->disk("public")
                            ->width(400)
                            ->height(400)
                            ->defaultImageUrl("data:image/svg+xml;charset=UTF-8," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><rect width="400" height="400" fill="%23f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-size="20">未上传衣物照片</text></svg>'))
                            ->columnSpan(1),
                        ImageEntry::make("scale_image")
                            ->label("秤重照片")
                            ->disk("public")
                            ->width(400)
                            ->height(400)
                            ->defaultImageUrl("data:image/svg+xml;charset=UTF-8," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><rect width="400" height="400" fill="%23f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-size="20">未上传秤重照片</text></svg>'))
                            ->columnSpan(1),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            "index"  => Pages\ListRecycleRecords::route("/"),
            "create" => Pages\CreateRecycleRecord::route("/create"),
            "view"   => Pages\ViewRecycleRecord::route("/{record}"),
            "edit"   => Pages\EditRecycleRecord::route("/{record}/edit"),
        ];
    }
}

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

/**
 * 【机构内部回收员工】回收记录管理
 *
 * ⚠️ 业务隔离声明：
 *   本 Resource 仅管理「机构内部回收员工」上门回收时登记的记录（recycle_records 表）。
 *   它与面向社区居民的「旧衣回收预约」模块（RecyclingOrderResource / recycling_orders 表）
 *   是两套完全独立的业务，数据表、模型、统计口径互不共享、互不污染。
 *   - 居民回收预约：RecyclingOrder 模型 → recycling_orders 表（居民端小程序 /api/recycle/reserve）
 *   - 内部回收登记：RecycleRecord 模型 → recycle_records 表（本 Resource）
 */
class RecycleRecordResource extends Resource
{
    protected static ?string $model = RecycleRecord::class;

    protected static ?string $navigationIcon = "heroicon-o-archive-box";

    protected static ?string $navigationLabel = "内部回收记录";

    protected static ?string $modelLabel = "内部回收记录";

    protected static ?string $pluralModelLabel = "内部回收记录";

    protected static ?string $navigationGroup = '回收业务';

    protected static ?int $navigationSort = 2;

    /** 列表行内现场照片仅预览前 N 张，避免几十张图挤在一行 */
    private const LIST_PREVIEW_COUNT = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make("回收信息")
                    ->schema([
                        Forms\Components\DatePicker::make("created_at")
                            ->label("回收日期")
                            ->default(now())
                            ->required()
                            ->maxDate(now())
                            ->helperText("可补录/修正回收日期，默认为今天；编辑时仅改日期、保留原录入时分秒。"),
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
                        Forms\Components\TextInput::make("amount")
                            ->label("金额")
                            ->numeric()
                            ->inputMode("decimal")
                            ->suffix("元")
                            ->minValue(0)
                            ->placeholder("选填"),
                        Forms\Components\Textarea::make("remark")
                            ->label("备注")
                            ->maxLength(500)
                            ->rows(2)
                            ->placeholder("选填，最多 500 字"),
                    ])->columns(2),

                Forms\Components\Section::make("现场照片")
                    ->schema([
                        Forms\Components\FileUpload::make("image_urls")
                            ->label("现场照片（最多 " . RecycleRecord::MAX_IMAGES . " 张）")
                            ->disk("public")
                            ->directory("recycle-records")
                            ->image()
                            ->multiple()
                            ->maxFiles(RecycleRecord::MAX_IMAGES)
                            ->maxSize(10240) // 单张最大 10MB
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
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
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                // 重量/金额为只读展示；修改请走行内「修改数值」弹窗（显式保存，避免误改、不重载图片）
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
                Tables\Columns\TextColumn::make("amount")
                    ->label("金额")
                    ->numeric(2)
                    ->suffix(" 元")
                    ->placeholder("-")
                    ->summarize(
                        Sum::make()
                            ->label("合计(元)")
                            ->numeric(2)
                    ),
                Tables\Columns\TextColumn::make("remark")
                    ->label("备注")
                    ->limit(30)
                    ->tooltip(fn ($state): string => $state ?? ""),
                Tables\Columns\ImageColumn::make("image_preview")
                    ->label("现场照片")
                    ->disk("public")
                    ->square()
                    ->width(70)
                    ->height(70)
                    ->stacked()
                    ->getStateUsing(
                        fn (RecycleRecord $record): array => array_slice(
                            $record->display_image_paths ?? [],
                            0,
                            self::LIST_PREVIEW_COUNT
                        )
                    )
                    ->defaultImageUrl("data:image/svg+xml;charset=UTF-8," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="70" height="70"><rect width="70" height="70" fill="%23f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-size="10">无图</text></svg>'))
                    ->extraImgAttributes(["loading" => "lazy"]),
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
                // 轻量修改：仅改重量/金额，弹窗内显式点「保存」才落库，避免误改且不重载现场照片
                Tables\Actions\Action::make("quickEdit")
                    ->label("修改数值")
                    ->icon("heroicon-o-pencil-square")
                    ->modalHeading("修改重量 / 金额")
                    ->modalSubmitActionLabel("保存")
                    ->fillForm(fn (RecycleRecord $record): array => [
                        "weight" => $record->weight,
                        "amount" => $record->amount,
                    ])
                    ->form([
                        Forms\Components\TextInput::make("weight")
                            ->label("重量")
                            ->numeric()
                            ->inputMode("decimal")
                            ->suffix(" kg")
                            ->required()
                            ->rules(["gt:0"]),
                        Forms\Components\TextInput::make("amount")
                            ->label("金额")
                            ->numeric()
                            ->inputMode("decimal")
                            ->suffix(" 元")
                            ->minValue(0)
                            ->placeholder("选填")
                            ->rules(["nullable", "numeric", "min:0"]),
                    ])
                    ->action(function (array $data, RecycleRecord $record): void {
                        $record->update([
                            "weight" => $data["weight"],
                            "amount" => $data["amount"] ?? null,
                        ]);
                    })
                    ->successNotificationMessage("保存成功"),
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
                        TextEntry::make("amount")
                            ->label("金额")
                            ->numeric(2)
                            ->suffix(" 元")
                            ->placeholder("-"),
                        TextEntry::make("remark")
                            ->label("备注")
                            ->placeholder("-")
                            ->columnSpanFull(),
                        TextEntry::make("created_at")
                            ->label("录入时间")
                            ->dateTime("Y-m-d H:i:s"),
                    ])->columns(2),

                Section::make("现场照片")
                    ->schema([
                        // display_image_paths 已合并 image_urls 与历史 clothes/scale，此处渲染全部图片
                        ImageEntry::make("display_image_paths")
                            ->label("现场照片")
                            ->disk("public")
                            ->width(280)
                            ->height(280)
                            ->defaultImageUrl("data:image/svg+xml;charset=UTF-8," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="280" height="280"><rect width="280" height="280" fill="%23f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-size="16">未上传照片</text></svg>'))
                            ->extraImgAttributes([
                                "onclick" => "window.open(this.src, '_blank')",
                                "class"   => "cursor-zoom-in",
                                "loading" => "lazy",
                            ]),
                    ]),
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

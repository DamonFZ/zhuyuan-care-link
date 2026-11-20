<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecycleBinInspectionResource\Pages;
use App\Models\RecycleBinInspection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * 【机构内部回收员工】回收箱巡检记录管理
 *
 * ⚠️ 业务隔离声明：
 *   本 Resource 仅管理「回收箱状态巡检」历史记录（recycle_bin_inspections 表）。
 *   它与面向社区居民的「旧衣回收预约」（RecyclingOrderResource / recycling_orders）、
 *   以及内部回收的「称重记录」（RecycleRecordResource / recycle_records）是三套
 *   完全独立的业务，数据表、模型、统计口径互不共享、互不污染。
 *
 * 管理策略：巡检数据为历史留痕，仅允许「查看 / 删除」，禁止在后台「创建 / 编辑」，
 * 避免误改一线员工现场提交的原始巡检数据。
 */
class RecycleBinInspectionResource extends Resource
{
    protected static ?string $model = RecycleBinInspection::class;

    protected static ?string $navigationIcon = "heroicon-o-clipboard-document-list";

    protected static ?string $navigationLabel = "回收箱巡检";

    protected static ?string $modelLabel = "回收箱巡检记录";

    protected static ?string $pluralModelLabel = "回收箱巡检记录";

    protected static ?string $navigationGroup = '回收业务';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        // 只读详情用：本资源不开放创建/编辑，表单字段全部禁用，仅作兜底渲染。
        return $form
            ->schema([
                Forms\Components\Section::make("基本信息")
                    ->schema([
                        Forms\Components\TextInput::make("bin_code")->label("箱号")->disabled(),
                        Forms\Components\TextInput::make("status")->label("状态")->disabled(),
                        Forms\Components\TextInput::make("user.name")->label("提交人员")->disabled(),
                        Forms\Components\TextInput::make("created_at")->label("提交时间")->disabled(),
                        Forms\Components\TextInput::make("latitude")->label("纬度")->disabled(),
                        Forms\Components\TextInput::make("longitude")->label("经度")->disabled(),
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

                Tables\Columns\TextColumn::make("bin_code")
                    ->label("箱号")
                    ->placeholder("未填")
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make("status")
                    ->label("状态")
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => RecycleBinInspection::STATUS_MAP[$state] ?? $state ?? "-")
                    ->color(fn (?string $state): string => match ($state) {
                        RecycleBinInspection::STATUS_GOOD    => "success",
                        RecycleBinInspection::STATUS_RUSTY   => "warning",
                        RecycleBinInspection::STATUS_DAMAGED => "danger",
                        default                               => "gray",
                    }),

                Tables\Columns\ImageColumn::make("images")
                    ->label("现场照片")
                    ->disk("public")
                    ->square()
                    ->width(60)
                    ->height(60)
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText()
                    ->defaultImageUrl("data:image/svg+xml;charset=UTF-8," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60"><rect width="60" height="60" fill="%23f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-size="10">无图</text></svg>'))
                    ->extraImgAttributes(["loading" => "lazy"]),

                Tables\Columns\TextColumn::make("latitude")
                    ->label("纬度")
                    ->placeholder("未获取"),

                Tables\Columns\TextColumn::make("longitude")
                    ->label("经度")
                    ->placeholder("未获取"),

                Tables\Columns\TextColumn::make("user.name")
                    ->label("提交人员")
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make("created_at")
                    ->label("提交时间")
                    ->dateTime("Y-m-d H:i:s")
                    ->sortable(),
            ])
            ->defaultSort("created_at", "desc")
            ->filters([
                // 按状态筛选（良好 / 生锈 / 损坏）
                Tables\Filters\SelectFilter::make("status")
                    ->label("状态")
                    ->options(RecycleBinInspection::STATUS_MAP),

                // 按提交人员筛选
                Tables\Filters\SelectFilter::make("user")
                    ->label("提交人员")
                    ->relationship("user", "name")
                    ->searchable()
                    ->preload(),

                // 按提交日期范围筛选
                Tables\Filters\Filter::make("created_at")
                    ->label("提交日期")
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
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading("确认删除该巡检记录？")
                    ->modalDescription("删除后不可恢复，相关操作日志仍会保留。"),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([]); // 移除顶部 Create 按钮：巡检数据仅由一线员工端提交，后台不录入
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make("基本信息")
                    ->schema([
                        TextEntry::make("bin_code")
                            ->label("箱号")
                            ->placeholder("未填"),
                        TextEntry::make("status")
                            ->label("状态")
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => RecycleBinInspection::STATUS_MAP[$state] ?? $state ?? "-")
                            ->color(fn (?string $state): string => match ($state) {
                                RecycleBinInspection::STATUS_GOOD    => "success",
                                RecycleBinInspection::STATUS_RUSTY   => "warning",
                                RecycleBinInspection::STATUS_DAMAGED => "danger",
                                default                               => "gray",
                            }),
                        TextEntry::make("user.name")->label("提交人员"),
                        TextEntry::make("created_at")
                            ->label("提交时间")
                            ->dateTime("Y-m-d H:i:s"),
                        TextEntry::make("latitude")
                            ->label("纬度")
                            ->placeholder("未获取"),
                        TextEntry::make("longitude")
                            ->label("经度")
                            ->placeholder("未获取"),
                    ])->columns(2),

                Section::make("现场照片")
                    ->schema([
                        // images 为 JSON 数组，ImageEntry 默认不限数量，会完整渲染第 1 ~ 第 N 张；
                        // 点击图片可在新标签页打开大图预览。
                        ImageEntry::make("images")
                            ->label("巡检照片")
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
            "index" => Pages\ListRecycleBinInspections::route("/"),
            "view"  => Pages\ViewRecycleBinInspection::route("/{record}"),
        ];
    }

    // 历史巡检数据仅允许查看 / 删除，禁止在后台创建 / 编辑
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use Filament\Tables\Actions\Action;
use Spatie\Activitylog\Models\Activity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = "heroicon-o-clipboard-document-list";

    protected static ?string $navigationLabel = "审计日志";

    protected static ?string $modelLabel = "审计记录";

    protected static ?string $pluralModelLabel = "审计日志";

    protected static ?int $navigationSort = 200;

    public static function form(Form $form): Form
    {
        // 审计日志只读，表单仅用于 View 页面
        return $form
            ->schema([
                Forms\Components\Section::make("基本信息")
                    ->schema([
                        Forms\Components\TextInput::make("id")
                            ->label("ID")
                            ->disabled(),
                        Forms\Components\TextInput::make("log_name")
                            ->label("日志组")
                            ->disabled(),
                        Forms\Components\TextInput::make("description")
                            ->label("操作描述")
                            ->disabled(),
                        Forms\Components\TextInput::make("event")
                            ->label("事件类型")
                            ->badge()
                            ->disabled(),
                    ])->columns(2),
                Forms\Components\Section::make("操作者")
                    ->schema([
                        Forms\Components\TextInput::make("causer.name")
                            ->label("操作人")
                            ->disabled()
                            ->placeholder("系统自动"),
                        Forms\Components\TextInput::make("causer_id")
                            ->label("操作人ID")
                            ->disabled(),
                        Forms\Components\TextInput::make("causer_type")
                            ->label("操作人类型")
                            ->disabled(),
                    ])->columns(3),
                Forms\Components\Section::make("变动对象")
                    ->schema([
                        Forms\Components\TextInput::make("subject_type")
                            ->label("模型类型")
                            ->disabled(),
                        Forms\Components\TextInput::make("subject_id")
                            ->label("模型ID")
                            ->disabled(),
                    ])->columns(2),
                Forms\Components\Section::make("属性变动")
                    ->schema([
                        Forms\Components\KeyValue::make("properties.attributes")
                            ->label("新值 (attributes)")
                            ->columnSpanFull()
                            ->disabled()
                            ->keyLabel("字段")
                            ->valueLabel("新值"),
                        Forms\Components\KeyValue::make("properties.old")
                            ->label("旧值 (old)")
                            ->columnSpanFull()
                            ->disabled()
                            ->keyLabel("字段")
                            ->valueLabel("旧值"),
                    ]),
                Forms\Components\Section::make("时间")
                    ->schema([
                        Forms\Components\TextInput::make("created_at")
                            ->label("操作时间")
                            ->disabled(),
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
                Tables\Columns\TextColumn::make("event")
                    ->label("事件")
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        "created" => "success",
                        "updated" => "warning",
                        "deleted" => "danger",
                        default => "gray",
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make("description")
                    ->label("操作描述")
                    ->searchable()
                    ->limit(60),
                Tables\Columns\TextColumn::make("subject_type")
                    ->label("模型类型")
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make("subject_id")
                    ->label("模型ID")
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make("causer.name")
                    ->label("操作人")
                    ->searchable()
                    ->placeholder("系统"),
                Tables\Columns\TextColumn::make("created_at")
                    ->label("操作时间")
                    ->dateTime("Y-m-d H:i:s")
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make("event")
                    ->label("事件类型")
                    ->options([
                        "created" => "新增",
                        "updated" => "修改",
                        "deleted" => "删除",
                    ]),
                Tables\Filters\Filter::make("has_subject")
                    ->label("有变动对象")
                    ->query(fn ($query) => $query->whereNotNull("subject_type")),
            ])
            ->actions([
                Action::make("view")
                    ->label("查看")
                    ->url(fn ($record) => Pages\ViewActivity::getUrl(["record" => $record]))
                    ->icon("heroicon-o-eye"),
                // ⛔ 彻底禁用：CreateAction / EditAction / DeleteAction
            ])
            ->bulkActions([
                // ⛔ 彻底禁用 BulkDeleteAction
            ])
            ->headerActions([]); // ⛔ 移除顶部 Create 按钮

    }

    public static function getPages(): array
    {
        return [
            "index" => Pages\ListActivities::route("/"),
            "view" => Pages\ViewActivity::route("/{record}"),
        ];
    }

    // 审计红线：禁止任何写入能力
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
}

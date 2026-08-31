<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SlideResource\Pages;
use App\Models\Slide;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SlideResource extends Resource
{
    protected static ?string $model = Slide::class;

    protected static ?string $navigationIcon = "heroicon-o-photo";

    protected static ?string $navigationLabel = "轮播图管理";

    protected static ?string $modelLabel = "轮播图";

    protected static ?string $pluralModelLabel = "轮播图管理";

    protected static ?string $navigationGroup = '业务台账';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('轮播图素材')
                    ->columns(1)
                    ->schema([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('轮播图片')
                            ->image()
                            ->directory('slides')
                            ->preserveFilenames()
                            ->maxSize(2048) // 2MB
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('16:9')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('title')
                            ->label('标题（可选）')
                            ->maxLength(50)
                            ->placeholder('留空则仅显示图片'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('排序权重')
                            ->numeric()
                            ->default(0)
                            ->hint('数值越小越靠前，可为负数置顶')
                            ->required(),
                        Forms\Components\Toggle::make('is_visible')
                            ->label('前台可见')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('预览')
                    ->square()
                    ->width(160)
                    ->height(90)
                    ->extraImgAttributes(['loading' => 'lazy']),
                Tables\Columns\TextColumn::make('title')
                    ->label('标题')
                    ->searchable()
                    ->placeholder('— 无标题 —'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('排序')
                    ->sortable()
                    ->badge()
                    ->color(fn ($v) => $v < 0 ? 'danger' : ($v == 0 ? 'info' : 'warning')),
                Tables\Columns\IconColumn::make('is_visible')
                    ->label('可见')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('可见性'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSlides::route('/'),
            'create' => Pages\CreateSlide::route('/create'),
            'edit'   => Pages\EditSlide::route('/{record}/edit'),
        ];
    }
}

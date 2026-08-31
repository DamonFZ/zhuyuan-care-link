<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VolunteerServiceTypeResource\Pages;
use App\Models\VolunteerServiceType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VolunteerServiceTypeResource extends Resource
{
    protected static ?string $model = VolunteerServiceType::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = '志愿岗位';

    protected static ?string $modelLabel = '岗位';

    protected static ?string $pluralModelLabel = '志愿岗位';

    protected static ?string $navigationGroup = '基础档案';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('岗位信息')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('岗位名称')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        Forms\Components\TextInput::make('base_reward_rate')
                            ->label('基础时薪 (元/小时)')
                            ->required()
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->default('1.00')
                            ->prefix('¥')
                            ->rules(['decimal:2', 'min:0']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('岗位名称')
                    ->searchable()
                    ->badge()
                    ->color(fn ($record) => (float)$record->base_reward_rate >= 1.2 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('base_reward_rate')
                    ->label('基础时薪')
                    ->numeric(2)
                    ->sortable()
                    ->prefix('¥')
                    ->suffix(' / 小时'),
                Tables\Columns\TextColumn::make('volunteer_records_count')
                    ->label('累计记录数')
                    ->counts('volunteerRecords'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i:s')
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
            'index'  => Pages\ListVolunteerServiceTypes::route('/'),
            'create' => Pages\CreateVolunteerServiceType::route('/create'),
            'edit'   => Pages\EditVolunteerServiceType::route('/{record}/edit'),
        ];
    }
}

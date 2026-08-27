<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = '居民管理';

    protected static ?string $modelLabel = '居民';

    protected static ?string $pluralModelLabel = '居民';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本信息')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('姓名')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('邮箱')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('openid')
                            ->label('OpenID')
                            ->disabled()
                            ->maxLength(255),
                    ])->columns(2),
                Forms\Components\Section::make('服务信息')
                    ->schema([
                        Forms\Components\TextInput::make('points')
                            ->label('消费金余额')
                            ->numeric()
                            ->prefix('¥')
                            ->inputMode('decimal')
                            ->rules(['numeric', 'min:0']),
                        Forms\Components\TextInput::make('volunteer_hours')
                            ->label('志愿时长(小时)')
                            ->numeric()
                            ->rules(['integer', 'min:0']),
                        Forms\Components\Toggle::make('is_captain')
                            ->label('网格队长'),
                    ])->columns(3),
                Forms\Components\Section::make('账号安全')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('密码')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('姓名')
                    ->searchable(),
                Tables\Columns\TextColumn::make('openid')
                    ->label('OpenID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('points')
                    ->label('消费金余额')
                    ->money('CNY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('volunteer_hours')
                    ->label('志愿时长')
                    ->suffix(' 小时')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_captain')
                    ->label('网格队长')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('注册时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_captain')
                    ->label('网格队长'),
                Tables\Filters\Filter::make('has_points')
                    ->label('有消费金')
                    ->query(fn (Builder $query): Builder => $query->where('points', '>', 0)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('add_points')
                    ->label('充值')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('充值金额')
                            ->numeric()
                            ->required()
                            ->prefix('¥'),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->increment('points', $data['amount']);
                    }),
                Tables\Actions\Action::make('add_hours')
                    ->label('增加时长')
                    ->form([
                        Forms\Components\TextInput::make('hours')
                            ->label('增加时长(小时)')
                            ->numeric()
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->increment('volunteer_hours', $data['hours']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

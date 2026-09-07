<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VolunteerActivityResource\Pages;
use App\Models\VolunteerActivity;
use App\Models\VolunteerServiceType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class VolunteerActivityResource extends Resource
{
    protected static ?string $model = VolunteerActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = '志愿活动';

    protected static ?string $modelLabel = '志愿活动';

    protected static ?string $pluralModelLabel = '志愿活动';

    protected static ?string $navigationGroup = '业务台账';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('活动信息')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('活动名称')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('volunteer_service_type_id')
                            ->label('岗位类型')
                            ->relationship('volunteerServiceType', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\DatePicker::make('activity_date')
                            ->label('活动日期')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('max_hours')
                            ->label('单场最大工时')
                            ->numeric()
                            ->default(4)
                            ->suffix('小时')
                            ->helperText('防挂机机制：若实际扫码时长超出此值，将按此上限结算'),
                        Forms\Components\Toggle::make('status')
                            ->label('是否启用')
                            ->default(true)
                            ->helperText('关闭后该活动二维码将无法被扫码签到/签退'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('活动名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('volunteerServiceType.name')
                    ->label('岗位类型')
                    ->badge()
                    ->placeholder('未设置'),
                Tables\Columns\TextColumn::make('activity_date')
                    ->label('活动日期')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_hours')
                    ->label('工时上限')
                    ->suffix(' 小时')
                    ->placeholder('4.00'),
                Tables\Columns\IconColumn::make('status')
                    ->label('启用')
                    ->boolean(),
                Tables\Columns\TextColumn::make('attendances_count')
                    ->label('累计打卡人次')
                    ->counts('attendances'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // 查看现场二维码（SVG Base64，支持截图打印）
                Tables\Actions\Action::make('view_qrcode')
                    ->label('查看二维码')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->modalHeading(fn ($record) => "活动二维码：{$record->title}")
                    ->modalContent(fn (VolunteerActivity $record) => view(
                        'filament.volunteer-activities.qrcode-modal',
                        [
                            'title'   => $record->title,
                            'token'   => $record->qrcode_token,
                            'qrImage' => self::buildQrBase64($record->qrcode_token),
                        ]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('关闭')
                    ->visible(fn (VolunteerActivity $record) => $record->status),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * 生成活动二维码的 SVG Base64 Data URL
     */
    public static function buildQrBase64(string $token): string
    {
        $svg = QrCode::size(320)->margin(1)->generate($token);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVolunteerActivities::route('/'),
            'create' => Pages\CreateVolunteerActivity::route('/create'),
            'edit'   => Pages\EditVolunteerActivity::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VolunteerAttendanceResource\Pages;
use App\Models\VolunteerAttendance;
use App\Models\VolunteerRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VolunteerAttendanceResource extends Resource
{
    protected static ?string $model = VolunteerAttendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = '业务台账';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = '打卡记录';

    protected static ?string $modelLabel = '打卡记录';

    protected static ?string $pluralModelLabel = '打卡记录';

    /**
     * 彻底只读：禁止新建、编辑、删除
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('志愿者')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('activity.title')
                    ->label('活动')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_in_time')
                    ->label('签到时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_out_time')
                    ->label('签退时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->placeholder('未签退'),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        default     => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed'  => '已签退',
                        'checked_in' => '进行中',
                        default      => $state,
                    }),
                Tables\Columns\TextColumn::make('remark')
                    ->label('备注')
                    ->limit(20)
                    ->placeholder('-'),
            ])
            ->defaultSort('check_in_time', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'checked_in' => '进行中',
                        'completed'  => '已签退',
                    ]),
            ])
            ->actions([
                // 管理员补签：仅对未签退(checked_in)的记录显示
                Tables\Actions\Action::make('manual_checkout')
                    ->label('管理员补签')
                    ->icon('heroicon-o-clock')
                    ->color('danger')
                    ->modalHeading('管理员补签签退')
                    ->visible(fn (VolunteerAttendance $record) => $record->status === 'checked_in')
                    ->form([
                        Forms\Components\DateTimePicker::make('check_out_time')
                            ->label('实际签退时间')
                            ->required()
                            ->default(now())
                            ->seconds(false),
                        Forms\Components\Textarea::make('remark')
                            ->label('补签备注')
                            ->required()
                            ->default('管理员人工核实补签')
                            ->rows(3),
                    ])
                    ->action(function (VolunteerAttendance $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            // 1) 更新打卡记录：写入签退时间 + 完成状态 + 备注
                            $record->update([
                                'check_out_time' => $data['check_out_time'],
                                'status'         => 'completed',
                                'remark'         => $data['remark'],
                            ]);

                            // 2) 计算服务时长（小时，保留两位小数）
                            $checkout = \Carbon\Carbon::parse($data['check_out_time']);
                            $checkin  = \Carbon\Carbon::parse($record->check_in_time);
                            $hours    = round($checkout->diffInMinutes($checkin) / 60, 2);

                            if ($hours > 0) {
                                // 3) 自动创建志愿台账记录
                                //    注意：multiplier / final_hours / reward_points 由
                                //    VolunteerRecordObserver::creating 按用户真实等级 +
                                //    岗位真实时薪重算，::created 自动发放消费金流水。
                                VolunteerRecord::create([
                                    'user_id'                   => $record->user_id,
                                    'volunteer_service_type_id' => $record->activity->volunteer_service_type_id,
                                    'base_hours'                => $hours,
                                    'title'                     => '补签结算: ' . $record->activity->title,
                                    'status'                    => 'completed',
                                ]);
                            }
                        });
                    })
                    ->successNotificationTitle('补签成功，结算已自动入账'),
            ])
            ->bulkActions([
                // 只读：无批量操作
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVolunteerAttendances::route('/'),
        ];
    }
}

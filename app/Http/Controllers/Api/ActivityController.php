<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VolunteerActivity;
use App\Models\VolunteerAttendance;
use App\Models\VolunteerRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 志愿活动扫码签到 / 签退 + 自动结算
 *
 * 同一个用户针对同一个活动的扫码行为：
 *   - 首次扫码：签到，写入 check_in_time
 *   - 再次扫码：签退，写入 check_out_time + 自动创建 VolunteerRecord
 *     （VolunteerRecordObserver 会按用户真实等级 + 岗位真实时薪重算
 *      multiplier / final_hours / reward_points，并自动发放消费金流水）
 */
class ActivityController extends Controller
{
    /**
     * POST /api/activity/scan
     * Body: { qrcode_token }
     */
    public function scan(Request $request)
    {
        $token = (string) $request->input('qrcode_token', '');
        if ($token === '') {
            return response()->json([
                'code'    => 422,
                'message' => '缺少 qrcode_token 参数',
            ], 422);
        }

        $userId = Auth::id();

        // 1) 查找启用的活动
        $activity = VolunteerActivity::where('qrcode_token', $token)
            ->where('status', true)
            ->first();

        if (!$activity) {
            return response()->json([
                'code'    => 404,
                'message' => '活动不存在或已停用',
            ], 404);
        }

        // 2) 查找该用户在该活动下未完成的签到记录
        $pending = VolunteerAttendance::where('user_id', $userId)
            ->where('volunteer_activity_id', $activity->id)
            ->where('status', 'checked_in')
            ->first();

        // 3) 首次扫码 → 签到
        if (!$pending) {
            $attendance = VolunteerAttendance::create([
                'user_id'                => $userId,
                'volunteer_activity_id'  => $activity->id,
                'check_in_time'          => now(),
                'status'                 => 'checked_in',
            ]);

            return response()->json([
                'code'    => 200,
                'message' => '签到成功',
                'data'    => [
                    'type' => 'check_in',
                    'time' => $attendance->check_in_time->toDateTimeString(),
                ],
            ], 200);
        }

        // 4) 再次扫码 → 签退 + 自动结算
        return DB::transaction(function () use ($pending, $activity, $userId) {
            // 行锁，防止并发重复签退
            $attendance = VolunteerAttendance::where('id', $pending->id)
                ->lockForUpdate()
                ->first();

            // 并发兜底：若已被另一请求签退，则直接当作本次已签退返回
            if (!$attendance || $attendance->status !== 'checked_in') {
                return response()->json([
                    'code'    => 200,
                    'message' => '签退成功',
                    'data'    => [
                        'type'   => 'check_out',
                        'hours'  => 0,
                        'points' => 0,
                    ],
                ], 200);
            }

            $checkIn  = $attendance->check_in_time;
            $checkOut = now();

            // 计算时长（小时，保留两位小数）
            $minutes = $checkIn->diffInMinutes($checkOut);
            $hours   = round($minutes / 60, 2);

            if ($hours <= 0) {
                return response()->json([
                    'code'    => 400,
                    'message' => '服务时长过短，无法结算',
                ], 400);
            }

            // 更新打卡记录
            $attendance->update([
                'check_out_time' => $checkOut,
                'status'         => 'completed',
            ]);

            // 自动创建志愿记录（Observer 兜底重算 + 发放消费金）
            $record = VolunteerRecord::create([
                'user_id'                   => $userId,
                'volunteer_service_type_id' => $activity->volunteer_service_type_id,
                'title'                     => "扫码自动生成：{$activity->title}",
                'base_hours'                => $hours,
                'status'                    => 'completed',
            ]);

            return response()->json([
                'code'    => 200,
                'message' => '签退成功',
                'data'    => [
                    'type'   => 'check_out',
                    'hours'  => (float) $record->final_hours,
                    'points' => (float) $record->reward_points,
                ],
            ], 200);
        });
    }
}

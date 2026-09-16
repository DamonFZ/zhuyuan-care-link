<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VolunteerActivity;
use App\Models\VolunteerAttendance;
use App\Models\VolunteerRecord;
use App\Models\VolunteerRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 志愿活动扫码签到 / 签退 + 自动结算
 *
 * 三道防刷阀门：
 *   1) 跨日失效：扫码日期必须与 activity_date 一致
 *   2) 单次闭环：同一用户对同一活动只能完成一次（已 completed 则提示已完成）
 *   3) 工时熔断：实际扫码时长超过 max_hours 时，按 max_hours 上限结算
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

        // 2) 第一道阀门：跨日失效
        //    活动日期未设置时视为历史活动，不做日期限制（向后兼容）
        if ($activity->activity_date && $activity->activity_date->toDateString() !== now()->toDateString()) {
            return response()->json([
                'code'    => 400,
                'message' => '不在活动日期范围内，无法打卡',
            ], 400);
        }

        // 3) 第二道阀门：单次闭环
        //    同一用户对同一活动只能完成一次，已 completed 则直接提示
        $completedRecord = VolunteerAttendance::where('user_id', $userId)
            ->where('volunteer_activity_id', $activity->id)
            ->where('status', 'completed')
            ->first();
        if ($completedRecord) {
            return response()->json([
                'code'    => 200,
                'message' => '您已完成本次志愿活动，辛苦啦！',
                'type'    => 'info',
            ], 200);
        }

        // 4) 查找该用户在该活动下未完成的签到记录
        $pending = VolunteerAttendance::where('user_id', $userId)
            ->where('volunteer_activity_id', $activity->id)
            ->where('status', 'checked_in')
            ->first();

        // 5) 首次扫码 → 签到
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

        // 6) 再次扫码 → 签退 + 自动结算（含第三道阀门：工时熔断）
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

            $checkin  = \Carbon\Carbon::parse($attendance->check_in_time);
            $checkout = now();

            // 计算实际时长（小时，保留两位小数）
            $actualHours = round($checkout->diffInMinutes($checkin) / 60, 2);
            $finalHours  = $actualHours;
            $remark      = '用户正常扫码签退';

            if ($actualHours <= 0) {
                return response()->json([
                    'code'    => 400,
                    'message' => '服务时长过短，无法结算',
                ], 400);
            }

            // 第三道阀门：工时熔断 —— 超过单场上限则按上限结算
            $maxHours = (float) $activity->max_hours;
            if ($maxHours > 0 && $actualHours > $maxHours) {
                $finalHours = $maxHours;
                $remark     = "达到单场上限，实际 {$actualHours}h，按 {$finalHours}h 结算";
            }

            // 更新打卡记录
            $attendance->update([
                'check_out_time' => $checkout,
                'status'         => 'completed',
                'remark'         => $remark,
            ]);

            // 自动创建志愿记录（Observer 兜底重算 + 发放消费金）
            // base_hours 严格使用熔断后的 $finalHours
            $record = VolunteerRecord::create([
                'user_id'                   => $userId,
                'volunteer_service_type_id' => $activity->volunteer_service_type_id,
                'title'                     => "扫码自动生成：{$activity->title}",
                'base_hours'                => $finalHours,
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

    /**
     * GET /api/activities —— 活动列表
     * 仅返回启用(status=true)的活动，按 activity_date 降序，分页。
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $activities = VolunteerActivity::query()
            ->where('status', true)
            ->orderBy('activity_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        // 手动组装分页结构，附带 is_ended 动态属性
        $items = $activities->getCollection()->map(function (VolunteerActivity $a) {
            return [
                'id'             => $a->id,
                'title'          => $a->title,
                'activity_date'  => $a->activity_date ? $a->activity_date->toDateString() : null,
                'max_hours'      => (float) $a->max_hours,
                'is_ended'       => $a->is_ended,
            ];
        })->values();

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => [
                'items'           => $items,
                'current_page'    => $activities->currentPage(),
                'per_page'        => $activities->perPage(),
                'total'           => $activities->total(),
                'last_page'       => $activities->lastPage(),
                'has_more_pages'  => $activities->hasMorePages(),
            ],
        ], 200);
    }

    /**
     * GET /api/activities/{id} —— 活动详情
     * 若用户已登录，附带返回 has_registered（当前用户是否已报名）。
     */
    public function show(int $id)
    {
        $activity = VolunteerActivity::query()
            ->where('status', true)
            ->find($id);

        if (!$activity) {
            return response()->json([
                'code'    => 404,
                'message' => '活动不存在或已停用',
            ], 404);
        }

        $userId = Auth::id();
        $hasRegistered = false;
        if ($userId) {
            $hasRegistered = VolunteerRegistration::where('user_id', $userId)
                ->where('volunteer_activity_id', $activity->id)
                ->where('status', 'registered')
                ->exists();
        }

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => [
                'id'              => $activity->id,
                'title'           => $activity->title,
                'activity_date'   => $activity->activity_date ? $activity->activity_date->toDateString() : null,
                'max_hours'       => (float) $activity->max_hours,
                'is_ended'        => $activity->is_ended,
                'has_registered'  => $hasRegistered,
            ],
        ], 200);
    }

    /**
     * POST /api/activities/{id}/register —— 用户报名活动
     * 校验：活动未结束 + 未重复报名，然后写入 volunteer_registrations。
     */
    public function register(int $id)
    {
        $userId = Auth::id();

        $activity = VolunteerActivity::query()
            ->where('status', true)
            ->find($id);

        if (!$activity) {
            return response()->json([
                'code'    => 404,
                'message' => '活动不存在或已停用',
            ], 404);
        }

        // 1) 活动已结束则不允许报名
        if ($activity->is_ended) {
            return response()->json([
                'code'    => 400,
                'message' => '活动已结束，无法报名',
            ], 400);
        }

        // 2) 重复报名校验
        $exists = VolunteerRegistration::where('user_id', $userId)
            ->where('volunteer_activity_id', $activity->id)
            ->where('status', 'registered')
            ->exists();

        if ($exists) {
            return response()->json([
                'code'    => 400,
                'message' => '您已报名该活动，请勿重复报名',
            ], 400);
        }

        // 3) 创建报名记录（unique 索引兜底防并发）
        try {
            VolunteerRegistration::create([
                'user_id'               => $userId,
                'volunteer_activity_id' => $activity->id,
                'status'                => 'registered',
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json([
                'code'    => 400,
                'message' => '您已报名该活动，请勿重复报名',
            ], 400);
        }

        return response()->json([
            'code'    => 200,
            'message' => '报名成功',
            'data'    => [
                'activity_id' => $activity->id,
                'status'      => 'registered',
            ],
        ], 200);
    }
}

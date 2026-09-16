<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointTransaction;
use App\Models\VolunteerAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Sanctum 保护：查询当前登录用户的完整个人资料
     * GET /api/user/profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('volunteerLevel');

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => $this->serializeUser($user),
        ], 200);
    }

    /**
     * Sanctum 保护：更新当前登录用户的个人信息
     * 请求字段：name (字符串)，avatar (字符串URL：已由 /api/upload 返回的线上地址)
     * POST /api/user/profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'   => 'nullable|string|max:50',
            'avatar' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => $validator->errors()->first(),
                'data'    => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $user = $request->user();

        $dirty = [];
        if (isset($validated['name']) && $validated['name'] !== '' && $validated['name'] !== $user->name) {
            $user->name = $validated['name'];
            $dirty[] = 'name';
        }
        if (isset($validated['avatar']) && $validated['avatar'] !== $user->avatar) {
            $user->avatar = $validated['avatar'];
            $dirty[] = 'avatar';
        }

        if (count($dirty) > 0) {
            $user->save();
        }

        $user->loadMissing('volunteerLevel');

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => [
                'updated_fields' => $dirty,
                'user'           => $this->serializeUser($user),
            ],
        ], 200);
    }

    /**
     * 消费金流水（当前用户）
     * GET /api/user/point-transactions?page=1
     */
    public function pointTransactions(Request $request)
    {
        $userId = $request->user()->id;

        $paginator = PointTransaction::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(15);

        $items = collect($paginator->items())->map(function ($tx) {
            return [
                'id'           => $tx->id,
                'event_name'   => $tx->event_name,
                'amount'       => (float) $tx->change_points,   // 正=收入，负=支出
                'new_points'   => (float) $tx->new_points,
                'created_at'   => (string) $tx->created_at,
            ];
        });

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => [
                'items'         => $items,
                'current_page'  => $paginator->currentPage(),
                'per_page'      => $paginator->perPage(),
                'total'         => $paginator->total(),
                'last_page'     => $paginator->lastPage(),
                'has_more_pages'=> $paginator->hasMorePages(),
            ],
        ], 200);
    }

    /**
     * 志愿打卡记录（当前用户）
     * GET /api/user/volunteer-attendances?page=1
     */
    public function volunteerAttendances(Request $request)
    {
        $userId = $request->user()->id;

        $paginator = VolunteerAttendance::with('activity')
            ->where('user_id', $userId)
            ->orderByDesc('check_in_time')
            ->paginate(15);

        $items = collect($paginator->items())->map(function ($att) {
            $serviceHours = null;
            if ($att->check_in_time && $att->check_out_time) {
                $minutes = $att->check_in_time->diffInMinutes($att->check_out_time);
                $serviceHours = round($minutes / 60, 2);
            }

            return [
                'id'              => $att->id,
                'status'          => $att->status,           // checked_in / completed
                'check_in_time'   => $att->check_in_time ? (string) $att->check_in_time : null,
                'check_out_time'  => $att->check_out_time ? (string) $att->check_out_time : null,
                'remark'          => $att->remark,
                'service_hours'   => $serviceHours,          // 已签退时返回小时数（保留两位）
                'activity'        => $att->activity ? [
                    'id'    => $att->activity->id,
                    'title' => $att->activity->title,
                ] : null,
            ];
        });

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => [
                'items'         => $items,
                'current_page'  => $paginator->currentPage(),
                'per_page'      => $paginator->perPage(),
                'total'         => $paginator->total(),
                'last_page'     => $paginator->lastPage(),
                'has_more_pages'=> $paginator->hasMorePages(),
            ],
        ], 200);
    }

    private function serializeUser($user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'avatar'     => $user->avatar ?: null,
            'email'      => $user->email,
            'role'       => $user->role ?: 'resident',
            'points'     => (float) ($user->points ?? 0),
            'volunteer_hours' => (float) ($user->volunteer_hours ?? 0),
            'volunteer_level_id' => $user->volunteer_level_id,
            'volunteerLevel' => $user->volunteerLevel ? [
                'id'         => $user->volunteerLevel->id,
                'name'       => $user->volunteerLevel->name,
                'multiplier' => (float) $user->volunteerLevel->multiplier,
            ] : null,
            'created_at' => (string) $user->created_at,
        ];
    }
}

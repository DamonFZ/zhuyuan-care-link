<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRecycleRecordRequest;
use App\Models\RecycleRecord;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * 【机构内部回收员工】回收记录 RESTful API
 *
 * 业务隔离：仅操作 recycle_records 表与 RecycleRecord 模型，
 * 不触碰居民回收的 recycling_orders / RecyclingOrder。
 *
 * 权限规则：
 *   - operator（操作员）：可查看全部记录
 *   - recycler（回收员）：仅可查看/提交自己的记录
 *   - 其他角色：拒绝访问
 */
class RecycleRecordController extends Controller
{
    /**
     * 提交一条回收记录
     * POST /api/recycle-records（需鉴权）
     */
    public function store(StoreRecycleRecordRequest $request)
    {
        $user = $request->user();

        if (!$this->isStaff($user)) {
            return $this->forbidden("无权提交回收记录");
        }

        $validated = $request->validated();

        $record = RecycleRecord::create([
            "user_id"       => $user->id,
            "clothes_image" => $validated["clothes_image"],
            "scale_image"   => $validated["scale_image"],
            "weight"        => $validated["weight"],
        ]);

        // Spatie LogsActivity 会自动记录 created 事件
        $record->load("user");

        return response()->json([
            "code"    => 201,
            "message" => "回收记录提交成功",
            "data"    => $this->serializeRecord($record),
        ], 201);
    }

    /**
     * 回收记录列表（分页）+ 汇总统计
     * GET /api/recycle-records?per_page=15&date=2026-11-20（需鉴权）
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$this->isStaff($user)) {
            return $this->forbidden("无权查看回收记录");
        }

        $isOperator = $user->role === User::ROLE_OPERATOR;

        // 基础作用域：操作员看全部，回收员只看自己
        $base = RecycleRecord::query()->with("user");
        if (!$isOperator) {
            $base->where("user_id", $user->id);
        }

        // 汇总统计（不受分页影响，基于当前可见范围）
        $todayTotal = (float) (clone $base)->whereDate("created_at", today())->sum("weight");
        $allTotal   = (float) (clone $base)->sum("weight");

        // 可选日期筛选
        $date = $request->input("date");
        if (!empty($date) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
            $base->whereDate("created_at", $date);
        }
        $filteredTotal = (float) (clone $base)->sum("weight");

        $perPage = (int) $request->input("per_page", 15);
        $perPage = max(1, min($perPage, 50));

        $paginator = $base->orderByDesc("created_at")->paginate($perPage);

        $items = collect($paginator->items())->map(fn (RecycleRecord $r) => $this->serializeRecord($r));

        return response()->json([
            "code"    => 200,
            "message" => "success",
            "data"    => [
                "items"           => $items,
                "current_page"    => $paginator->currentPage(),
                "per_page"        => $paginator->perPage(),
                "total"           => $paginator->total(),
                "last_page"       => $paginator->lastPage(),
                "has_more_pages"  => $paginator->hasMorePages(),
                "summary"         => [
                    "today_total_weight" => round($todayTotal, 2), // 今日总重（可见范围）
                    "total_weight"       => round($allTotal, 2),   // 全部总重（可见范围）
                    "date"               => $date ?: null,
                    "date_total_weight"  => $date ? round($filteredTotal, 2) : null,
                ],
            ],
        ], 200);
    }

    /**
     * 单条回收记录详情
     * GET /api/recycle-records/{id}（需鉴权）
     */
    public function show(Request $request, int $id)
    {
        $user = $request->user();

        if (!$this->isStaff($user)) {
            return $this->forbidden("无权查看回收记录");
        }

        $record = RecycleRecord::with("user")->find($id);

        if (!$record) {
            return response()->json([
                "code"    => 404,
                "message" => "回收记录不存在",
                "data"    => null,
            ], 404);
        }

        // 越权：回收员只能看自己的记录
        $isOperator = $user->role === User::ROLE_OPERATOR;
        if (!$isOperator && $record->user_id !== $user->id) {
            return $this->forbidden("无权查看他人的回收记录");
        }

        return response()->json([
            "code"    => 200,
            "message" => "success",
            "data"    => $this->serializeRecord($record),
        ], 200);
    }

    /**
     * 仅内部员工（回收员 / 操作员）可访问
     */
    private function isStaff(User $user): bool
    {
        return in_array($user->role, [User::ROLE_RECYCLER, User::ROLE_OPERATOR], true);
    }

    private function forbidden(string $message)
    {
        return response()->json([
            "code"    => 403,
            "message" => $message,
            "data"    => null,
        ], 403);
    }

    /**
     * 统一序列化单条记录（含完整图片可访问 URL 与回收人员信息）
     */
    private function serializeRecord(RecycleRecord $record): array
    {
        return [
            "id"               => $record->id,
            "weight"           => (float) $record->weight,
            "created_at"       => $record->created_at ? $record->created_at->format("Y-m-d H:i:s") : null,
            "date"             => $record->created_at ? $record->created_at->format("Y-m-d") : null,
            "clothes_image"    => $record->clothes_image,
            "clothes_image_url"=> $record->clothes_image_url,
            "scale_image"      => $record->scale_image,
            "scale_image_url"  => $record->scale_image_url,
            "user"             => $record->user ? [
                "id"   => $record->user->id,
                "name" => $record->user->name,
                "role" => $record->user->role,
            ] : null,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRecycleRecordRequest;
use App\Http\Requests\Api\UpdateRecycleRecordRequest;
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
            "image_urls"    => $validated["image_urls"],
            "weight"        => $validated["weight"],
            "amount"        => $validated["amount"] ?? null,
            "remark"        => $validated["remark"] ?? null,
            // 旧字段兼容：前端若传则写入，不传为 null（向后兼容历史 App）
            "clothes_image" => $validated["clothes_image"] ?? null,
            "scale_image"   => $validated["scale_image"] ?? null,
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
     * 编辑更新一条回收记录
     * PUT / PATCH /api/recycle-records/{id}（需鉴权）
     *
     * 业务规则：
     *   - 记录不存在 → 404
     *   - 非本人提交（user_id != 当前用户 id）→ 403
     *   - 仅允许本人修改；操作员亦不可代改他人记录（若后续有需求再放开）
     *
     * Spatie LogsActivity（logFillable + logOnlyDirty）会自动记录 updated 事件，
     * 仅写入发生变更的字段，便于后台核查。
     */
    public function update(UpdateRecycleRecordRequest $request, int $id)
    {
        $user = $request->user();

        if (!$this->isStaff($user)) {
            return $this->forbidden("无权操作回收记录");
        }

        $record = RecycleRecord::with("user")->find($id);
        if (!$record) {
            return response()->json([
                "code"    => 404,
                "message" => "回收记录不存在",
                "data"    => null,
            ], 404);
        }

        // 本人隔离：仅记录提交者本人可编辑
        if ($record->user_id !== $user->id) {
            return $this->forbidden("无权操作他人的回收记录");
        }

        $validated = $request->validated();

        $record->update([
            "image_urls" => $validated["image_urls"],
            "weight"     => $validated["weight"],
            "amount"     => $validated["amount"] ?? null,
            "remark"     => $validated["remark"] ?? null,
        ]);

        return response()->json([
            "code"    => 200,
            "message" => "回收记录更新成功",
            "data"    => $this->serializeRecord($record),
        ], 200);
    }

    /**
     * 删除一条回收记录
     * DELETE /api/recycle-records/{id}（需鉴权）
     *
     * 业务规则：
     *   - 记录不存在 → 404
     *   - 非本人提交 → 403
     *   - 创建时间距今已满 24 小时 → 403（仅删除受限，编辑不受此限）
     *
     * 模型未启用 SoftDeletes，此处执行硬删除；
     * Spatie LogsActivity 会自动记录 deleted 事件。
     */
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();

        if (!$this->isStaff($user)) {
            return $this->forbidden("无权操作回收记录");
        }

        $record = RecycleRecord::with("user")->find($id);
        if (!$record) {
            return response()->json([
                "code"    => 404,
                "message" => "回收记录不存在",
                "data"    => null,
            ], 404);
        }

        // 本人隔离：仅记录提交者本人可删除
        if ($record->user_id !== $user->id) {
            return $this->forbidden("无权操作他人的回收记录");
        }

        // 24 小时删除限制：created_at 早于等于（当前时间 - 24h）即禁止删除
        if ($record->created_at && $record->created_at->lte(now()->subHours(24))) {
            return response()->json([
                "code"    => 403,
                "message" => "超过24小时的记录不能删除",
                "data"    => null,
            ], 403);
        }

        $record->delete();

        return response()->json([
            "code"    => 200,
            "message" => "删除成功",
            "data"    => null,
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
            "id"                => $record->id,
            "weight"            => (float) $record->weight,
            "amount"            => $record->amount !== null ? (float) $record->amount : null,
            "remark"            => $record->remark,
            "image_urls"        => is_array($record->image_urls) ? $record->image_urls : [],
            "full_image_urls"   => $record->full_image_urls, // 全部图片完整 URL（含历史旧字段兼容）
            "created_at"        => $record->created_at ? $record->created_at->format("Y-m-d H:i:s") : null,
            "date"              => $record->created_at ? $record->created_at->format("Y-m-d") : null,
            // 旧字段兼容：历史 App 仍可能读取
            "clothes_image"     => $record->clothes_image,
            "clothes_image_url" => $record->clothes_image_url,
            "scale_image"       => $record->scale_image,
            "scale_image_url"   => $record->scale_image_url,
            "user"              => $record->user ? [
                "id"   => $record->user->id,
                "name" => $record->user->name,
                "role" => $record->user->role,
            ] : null,
        ];
    }
}

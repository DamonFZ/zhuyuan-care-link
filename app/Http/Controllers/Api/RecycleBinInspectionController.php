<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRecycleBinInspectionRequest;
use App\Models\RecycleBinInspection;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * 【机构内部回收员工】回收箱巡检 RESTful API
 *
 * 业务隔离：仅操作 recycle_bin_inspections 表与 RecycleBinInspection 模型，
 * 不触碰居民回收的 recycling_orders / RecyclingOrder，也不影响 recycle_records。
 *
 * 权限规则（与 RecycleRecordController 对齐）：
 *   - operator（操作员）：可查看全部巡检记录
 *   - recycler（回收员）：仅可查看/提交自己的记录
 *   - 其他角色：拒绝访问
 */
class RecycleBinInspectionController extends Controller
{
    /**
     * 提交一条回收箱巡检记录
     * POST /api/recycle-bin-inspections（需鉴权）
     */
    public function store(StoreRecycleBinInspectionRequest $request)
    {
        $user = $request->user();

        if (!$this->isStaff($user)) {
            return $this->forbidden("无权提交回收箱巡检记录");
        }

        $validated = $request->validated();

        $record = RecycleBinInspection::create([
            "user_id"    => $user->id,
            "bin_code"   => $validated["bin_code"] ?? null,
            "status"     => $validated["status"] ?? RecycleBinInspection::STATUS_GOOD,
            "images"     => $validated["images"],
            "latitude"   => $validated["latitude"] ?? null,
            "longitude"  => $validated["longitude"] ?? null,
        ]);

        // Spatie LogsActivity 自动记录 created 事件
        $record->load("user");

        return response()->json([
            "code"    => 201,
            "message" => "巡检记录提交成功",
            "data"    => $this->serialize($record),
        ], 201);
    }

    /**
     * 巡检记录列表（分页，按时间倒序，支持箱号/状态筛选）
     * GET /api/recycle-bin-inspections?per_page=15&bin_code=ZS0001&status=good（需鉴权）
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$this->isStaff($user)) {
            return $this->forbidden("无权查看回收箱巡检记录");
        }

        $isOperator = $user->role === User::ROLE_OPERATOR;

        $query = RecycleBinInspection::query()->with("user");

        // 权限：操作员看全部，回收员只看自己
        if (!$isOperator) {
            $query->where("user_id", $user->id);
        }

        // 可选筛选：箱号（模糊匹配）
        $binCode = $request->input("bin_code");
        if (!empty($binCode)) {
            $query->where("bin_code", "like", "%" . trim($binCode) . "%");
        }

        // 可选筛选：状态（精确匹配）
        $status = $request->input("status");
        if (!empty($status) && isset(RecycleBinInspection::STATUS_MAP[$status])) {
            $query->where("status", $status);
        }

        $perPage = (int) $request->input("per_page", 15);
        $perPage = max(1, min($perPage, 50));

        $paginator = $query->orderByDesc("created_at")->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (RecycleBinInspection $r) => $this->serialize($r));

        return response()->json([
            "code"    => 200,
            "message" => "success",
            "data"    => [
                "items"          => $items,
                "current_page"   => $paginator->currentPage(),
                "per_page"       => $paginator->perPage(),
                "total"          => $paginator->total(),
                "last_page"      => $paginator->lastPage(),
                "has_more_pages" => $paginator->hasMorePages(),
            ],
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
     * 统一序列化单条记录（含状态文案、多图完整 URL、定位、提交人信息）
     */
    private function serialize(RecycleBinInspection $record): array
    {
        return [
            "id"          => $record->id,
            "bin_code"    => $record->bin_code,
            "status"      => $record->status,
            "status_text" => $record->status_text,
            "images"      => $record->images ?? [],
            "images_urls" => $record->images_urls,
            "latitude"    => $record->latitude !== null ? (float) $record->latitude : null,
            "longitude"   => $record->longitude !== null ? (float) $record->longitude : null,
            "created_at"  => $record->created_at ? $record->created_at->format("Y-m-d H:i:s") : null,
            "user"        => $record->user ? [
                "id"   => $record->user->id,
                "name" => $record->user->name,
                "role" => $record->user->role,
            ] : null,
        ];
    }
}

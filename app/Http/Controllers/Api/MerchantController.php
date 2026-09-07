<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * 商户端：扫码后的扣款执行
 * POST /api/merchant/deduct
 * 鉴权：auth:sanctum，且当前登录用户 role 必须为 merchant
 *
 * 核心逻辑：
 *   1) 校验当前操作用户必须是 merchant；
 *   2) 校验 target_user_id（被核销居民）与 amount（>0）；
 *   3) 调用 $targetUser->modifyPoints(-$amount, '商户核销：xxx')，
 *      内部已用 DB 事务 + 行锁 + bccomp 保证原子性与余额不足拒绝；
 *   4) 返回扣款后双方的余额快照。
 */
class MerchantController extends Controller
{
    public function deduct(Request $request)
    {
        // 1) 角色校验：必须是商户
        $operator = Auth::user();
        if (!$operator || ($operator->role ?? 'resident') !== 'merchant') {
            return response()->json([
                'code'    => 403,
                'message' => '仅商户账号可执行核销扣款',
            ], 403);
        }

        // 2) 参数校验
        $validator = Validator::make($request->all(), [
            'target_user_id' => 'required|integer|min:1',
            'amount'         => 'required|numeric|gt:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => $validator->errors()->first(),
                'data'    => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $targetUserId = (int) $validated['target_user_id'];
        $amount       = $validated['amount'];

        // 3) 查找被核销居民
        $targetUser = User::find($targetUserId);
        if (!$targetUser) {
            return response()->json([
                'code'    => 404,
                'message' => '目标用户不存在',
            ], 404);
        }

        // 4) 禁止商户扣自己（防误操作）
        if ($targetUser->id === $operator->id) {
            return response()->json([
                'code'    => 400,
                'message' => '不能核销自己',
            ], 400);
        }

        // 5) 执行扣款（modifyPoints 内部已做行锁 + 余额不足抛异常）
        try {
            $eventName = '商户核销：' . ($operator->name ?: '商户');
            $ok = $targetUser->modifyPoints(-$amount, $eventName);

            if (!$ok) {
                return response()->json([
                    'code'    => 500,
                    'message' => '扣款失败，请重试',
                ], 500);
            }
        } catch (\Throwable $e) {
            // modifyPoints 对"余额不足"等会 throw RuntimeException
            return response()->json([
                'code'    => 400,
                'message' => $e->getMessage() ?: '扣款失败',
            ], 400);
        }

        // 6) 返回扣款结果（含双方最新余额，便于前端展示）
        return response()->json([
            'code'    => 200,
            'message' => '扣款成功',
            'data'    => [
                'target_user' => [
                    'id'     => $targetUser->id,
                    'name'   => $targetUser->name,
                    'points' => (float) $targetUser->points, // modifyPoints 内部已同步本地属性
                ],
                'amount'   => (float) $amount,
                'operator' => [
                    'id'   => $operator->id,
                    'name' => $operator->name,
                    'role' => $operator->role,
                ],
            ],
        ], 200);
    }
}

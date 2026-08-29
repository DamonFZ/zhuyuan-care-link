<?php

namespace App\Observers;

use App\Models\RecyclingOrder;

class RecyclingOrderObserver
{
    /**
     * 订单创建后，若为积分结算且状态已完成，则自动给用户加积分
     * 同时写入消费金流水（modifyPoints 内部已处理事务）
     */
    public function created(RecyclingOrder $order): void
    {
        if (
            $order->reward_type === "points"
            && $order->status === "completed"
            && $order->reward_amount > 0
        ) {
            $user = $order->user;
            if ($user) {
                $weightFormatted = number_format((float)$order->weight, 2, ".", "");
                $user->modifyPoints(
                    $order->reward_amount,
                    "旧衣回收 {$weightFormatted} 斤"
                );
            }
        }
    }

    /**
     * 订单更新后的一致性兜底：
     * 例如管理员在后台把状态从 pending 改成 completed，或修改 reward_amount
     * 这里只补"变成 completed 且尚未入账"的场景，避免重复记账。
     * 由于目前没有 flow_id 字段，我们通过同 user + 同 order 描述 + 同金额 + 同日来判断是否已记账。
     * 如后续需要更强保证，建议在 RecyclingOrder 增加 point_transaction_id 字段。
     */
    public function updated(RecyclingOrder $order): void
    {
        if (
            $order->reward_type === "points"
            && $order->status === "completed"
            && $order->reward_amount > 0
        ) {
            // 幂等：查该用户当日是否已经为该订单入账过
            $exists = $order->user->pointTransactions()
                ->whereDate("created_at", today())
                ->where("change_points", $order->reward_amount)
                ->where("event_name", "旧衣回收 " . number_format((float)$order->weight, 2, ".", "") . " 斤")
                ->exists();

            if (!$exists) {
                $order->user->modifyPoints(
                    $order->reward_amount,
                    "旧衣回收 " . number_format((float)$order->weight, 2, ".", "") . " 斤"
                );
            }
        }
    }
}

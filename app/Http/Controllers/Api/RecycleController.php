<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecyclingOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RecycleController extends Controller
{
    /**
     * 上门回收预约
     * POST /api/recycle/reserve
     *
     * @param Request $request
     *   - estimated_weight: string (必填，如 '3~20kg')
     *   - appointment_time: string (必填，预约上门时间，datetime 字符串)
     *   - images: array (可选，图片 URL 数组)
     *   - remark: string (可选，备注)
     */
    public function reserve(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'estimated_weight' => 'required|string|max:50',
            'appointment_time' => 'required|date',
            'images'           => 'nullable|array',
            'images.*'         => 'nullable|string|max:500',
            'remark'           => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => $validator->errors()->first(),
                'data'    => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $order = RecyclingOrder::create([
            'user_id'          => $request->user()->id,
            'estimated_weight' => $validated['estimated_weight'],
            'appointment_time' => $validated['appointment_time'],
            'images'           => $validated['images'] ?? [],
            'remark'           => $validated['remark'] ?? null,
            'status'           => 'pending',
            // 旧字段兼容填空，避免 NOT NULL 约束
            'weight'           => 0,
            'reward_type'      => 'points',
            'reward_amount'    => 0,
        ]);

        return response()->json([
            'code'    => 200,
            'message' => '预约成功，工作人员将尽快联系您',
            'data'    => [
                'id'               => $order->id,
                'estimated_weight' => $order->estimated_weight,
                'appointment_time' => (string) $order->appointment_time,
                'status'           => $order->status,
            ],
        ], 200);
    }
}

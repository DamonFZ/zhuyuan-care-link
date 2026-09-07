<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * 动态核销码（居民端展示 / 商户端扫码解析）
 *
 * 设计：
 *   - generate：用 Str::random(32) 生成一个一次性 Token，
 *               与当前登录居民的 user_id 绑定，写入缓存 60s。
 *               60s 过期后 token 自动失效，防止截图盗用。
 *   - resolve：商户扫码拿到 token 后，凭 token 查出被核销居民的
 *               id / name / avatar / points，用于后续扣款。
 */
class QrCodeController extends Controller
{
    /**
     * 居民：生成一个 60s 有效的动态核销 Token
     * GET /api/qrcode/generate
     * 鉴权：auth:sanctum（当前登录用户即被核销用户）
     */
    public function generate(Request $request)
    {
        $token = Str::random(32);
        $userId = Auth::id();
        $ttl = 60; // 秒

        Cache::put('qr_token_' . $token, $userId, now()->addSeconds($ttl));

        // 由后端直接生成 SVG 二维码并转 Base64，前端仅用 <image> 展示，
        // 彻底规避小程序 Canvas 2D API 在不同基础库下的兼容性问题。
        $qrSvg = QrCode::size(300)->margin(1)->generate($token);
        $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => [
                'token'      => $token,
                'expires_at' => now()->addSeconds($ttl)->toDateTimeString(),
                'ttl'        => $ttl,
                'qr_image'   => $qrBase64,
            ],
        ], 200);
    }

    /**
     * 商户：根据扫码得到的 token 解析出被核销用户的资料
     * POST /api/qrcode/resolve
     * Body: { token }
     * 鉴权：auth:sanctum（商户登录态即可；角色限制放到扣款接口）
     */
    public function resolve(Request $request)
    {
        $token = (string) $request->input('token', '');
        if ($token === '') {
            return response()->json([
                'code'    => 422,
                'message' => '缺少 token 参数',
            ], 422);
        }

        $userId = Cache::get('qr_token_' . $token);
        if (empty($userId)) {
            return response()->json([
                'code'    => 400,
                'message' => '二维码已过期',
            ], 400);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'code'    => 404,
                'message' => '用户不存在',
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => [
                'id'     => $user->id,
                'name'   => $user->name ?: '微信用户',
                'avatar' => $user->avatar ?: null,
                'points' => (float) ($user->points ?? 0),
            ],
        ], 200);
    }
}

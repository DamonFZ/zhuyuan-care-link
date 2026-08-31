<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function wechatLogin(Request $request)
    {
        $request->validate(["code" => "required|string"]);

        $appid = config("wechat.miniprogram.appid");
        $secret = config("wechat.miniprogram.secret");

        $response = Http::get("https://api.weixin.qq.com/sns/jscode2session", [
            "appid" => $appid,
            "secret" => $secret,
            "js_code" => $request->code,
            "grant_type" => "authorization_code",
        ]);

        $data = $response->json();

        if (!isset($data["openid"])) {
            return response()->json([
                "code" => 400,
                "message" => "WeChat login failed: " . ($data["errmsg"] ?? "Unknown error"),
                "data" => null,
            ], 400);
        }

        $openid = $data["openid"];
        $sessionKey = $data["session_key"] ?? null;

        $user = User::firstOrCreate(
            ["openid" => $openid],
            [
                "name" => "微信用户_" . substr($openid, -6),
                "role" => "resident",
            ]
        );

        $user->session_key = $sessionKey;
        // 兜底：历史老用户未设 role 时，使用数据库 DEFAULT resident。
        if (empty($user->role)) {
            $user->role = "resident";
        }
        $user->save();

        // 预加载志愿者等级信息并标准化字段给小程序端
        $user->loadMissing("volunteerLevel");

        $token = $user->createToken("carelink-app")->plainTextToken;

        return response()->json([
            "code" => 200,
            "message" => "success",
            "data" => [
                "token"          => $token,
                "role"           => $user->role,
                "points"         => (float) ($user->points ?? 0),
                "volunteer_hours"=> (float) ($user->volunteer_hours ?? 0),
                "volunteerLevel" => $user->volunteerLevel ? [
                    "id"         => $user->volunteerLevel->id,
                    "name"       => $user->volunteerLevel->name,
                    "multiplier" => (float) $user->volunteerLevel->multiplier,
                ] : null,
                "user" => [
                    "id"        => $user->id,
                    "name"      => $user->name,
                    "openid"    => $user->openid,
                    "email"     => $user->email,
                    "role"      => $user->role,
                    "points"    => (float) ($user->points ?? 0),
                    "volunteer_hours" => (float) ($user->volunteer_hours ?? 0),
                    "volunteer_level_id" => $user->volunteer_level_id,
                    "created_at"=> (string) $user->created_at,
                ],
            ],
        ], 200);
    }
}

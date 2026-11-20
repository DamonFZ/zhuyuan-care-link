<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    /**
     * 员工账号密码登录（回收业务 App / 后台操作员）
     * 复用 users 表：以 email（或 account 别名）+ password 登录。
     * 居民用户通过微信 openid 登录、password 为 NULL，无法走此接口，天然隔离。
     *
     * POST /api/login
     */
    public function login(LoginRequest $request)
    {
        $loginField = $request->input("email") ?: $request->input("account");
        $password   = $request->input("password");

        $user = User::where("email", $loginField)->first();

        if (!$user || empty($user->password) || !Hash::check($password, $user->password)) {
            return response()->json([
                "code"    => 401,
                "message" => "账号或密码错误",
                "data"    => null,
            ], 401);
        }

        $token = $user->createToken("recycle-app")->plainTextToken;

        return response()->json([
            "code"    => 200,
            "message" => "登录成功",
            "data"    => [
                "token" => $token,
                "user"  => $this->serializeUser($user),
            ],
        ], 200);
    }

    /**
     * 退出登录：撤销当前请求使用的 Sanctum Token
     * POST /api/logout（需鉴权）
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            "code"    => 200,
            "message" => "已退出登录",
            "data"    => null,
        ], 200);
    }

    /**
     * 获取当前登录用户信息（供 App"我"页展示）
     * GET /api/user（需鉴权）
     */
    public function me(Request $request)
    {
        return response()->json([
            "code"    => 200,
            "message" => "success",
            "data"    => $this->serializeUser($request->user()),
        ], 200);
    }

    private function serializeUser(User $user): array
    {
        return [
            "id"         => $user->id,
            "name"       => $user->name,
            "avatar"     => $user->avatar ?: null,
            "email"      => $user->email,
            "role"       => $user->role ?: "resident",
            "role_text"  => match ($user->role) {
                User::ROLE_RESIDENT => "社区居民",
                User::ROLE_MERCHANT => "核销商户",
                User::ROLE_OPERATOR => "操作员",
                User::ROLE_RECYCLER => "回收员",
                default             => $user->role ?: "未知",
            },
            "created_at" => (string) $user->created_at,
        ];
    }
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

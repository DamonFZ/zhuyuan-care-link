<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (前缀 /api)
|--------------------------------------------------------------------------
*/

// ===== 公开路由（无需鉴权）=====
Route::post("/wechat/login", [AuthController::class, "wechatLogin"]);
Route::get("/slides",       [HomeController::class, "getSlides"]);


// ===== Sanctum 受保护路由 =====
Route::middleware("auth:sanctum")->group(function () {

    Route::get("/user", function (Request $request) {
        return $request->user();
    });

    // 头像上传：返回 { url }
    Route::post('/upload',       [UploadController::class, 'uploadImage']);

    // 个人资料（name + avatar）更新
    Route::post('/user/profile', [UserController::class, 'updateProfile']);

    // 个人资料查询（结构化返回，含等级/头像）
    Route::get("/user/profile",  [UserController::class, 'getProfile']);

    // 消费金流水（分页，按时间倒序）
    Route::get("/user/point-transactions", [UserController::class, 'pointTransactions']);

    // 志愿打卡记录（分页，按签到时间倒序，预加载活动）
    Route::get("/user/volunteer-attendances", [UserController::class, 'volunteerAttendances']);

    // ===== 动态核销码 & 商户扫码扣款闭环 =====
    // 居民：生成 60s 动态核销 Token
    Route::get('/qrcode/generate', [QrCodeController::class, 'generate']);
    // 商户：根据 token 解析出被核销用户资料
    Route::post('/qrcode/resolve', [QrCodeController::class, 'resolve']);
    // 商户：执行扣款（内部调用 $user->modifyPoints，行锁+余额校验）
    Route::post('/merchant/deduct', [MerchantController::class, 'deduct']);

    // ===== 志愿活动扫码签到/签退 + 自动结算 =====
    Route::post('/activity/scan', [ActivityController::class, 'scan']);
});

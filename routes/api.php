<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\RecycleBinInspectionController;
use App\Http\Controllers\Api\RecycleController;
use App\Http\Controllers\Api\RecycleRecordController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (前缀 /api)
|--------------------------------------------------------------------------
*/

// ===== 公开路由（无需鉴权）=====
// 居民微信登录（小程序）
Route::post("/wechat/login", [AuthController::class, "wechatLogin"]);
// 员工账号密码登录（回收业务 App / 后台操作员，复用 users 表 email+password）
Route::post("/login",        [AuthController::class, "login"]);
Route::get("/slides",       [HomeController::class, "getSlides"]);
// 志愿活动列表（首页展示，无需登录）
Route::get("/activities",   [ActivityController::class, "index"]);


// ===== Sanctum 受保护路由 =====
Route::middleware("auth:sanctum")->group(function () {

    // 当前登录用户信息（App"我"页 / 后台共用，返回结构化 user）
    Route::get("/user",  [AuthController::class, "me"]);
    // 退出登录：撤销当前 Token
    Route::post("/logout", [AuthController::class, "logout"]);

    // 志愿活动详情（附带当前用户 has_registered）与报名
    Route::get("/activities/{id}",          [ActivityController::class, "show"]);
    Route::post("/activities/{id}/register", [ActivityController::class, "register"]);

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

    // ===== 上门回收预约（居民端，recycling_orders） =====
    Route::post('/recycle/reserve', [RecycleController::class, 'reserve']);

    // ===== 内部回收员工业务（recycle_records）—— 与上方居民回收完全隔离 =====
    // 提交回收记录 / 历史列表+汇总统计 / 单条详情
    Route::post('/recycle-records',     [RecycleRecordController::class, 'store']);
    Route::get('/recycle-records',      [RecycleRecordController::class, 'index']);
    Route::get('/recycle-records/{id}', [RecycleRecordController::class, 'show']);
    // 编辑更新（PUT / PATCH 均支持）/ 删除（仅本人 + 创建未满 24 小时可删）
    Route::match(['put', 'patch'], '/recycle-records/{id}', [RecycleRecordController::class, 'update']);
    Route::delete('/recycle-records/{id}',                  [RecycleRecordController::class, 'destroy']);

    // ===== 回收箱巡检（recycle_bin_inspections）—— 独立模块，与上述互不干扰 =====
    // 提交巡检记录 / 巡检列表（分页+筛选）
    Route::post('/recycle-bin-inspections', [RecycleBinInspectionController::class, 'store']);
    Route::get('/recycle-bin-inspections',  [RecycleBinInspectionController::class, 'index']);
});

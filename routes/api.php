<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HomeController;
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
});

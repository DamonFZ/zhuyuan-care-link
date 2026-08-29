<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post("/wechat/login", [AuthController::class, "wechatLogin"]);

// Protected routes
Route::middleware("auth:sanctum")->group(function () {
    Route::get("/user", function (Request $request) {
        return $request->user();
    });

    Route::get("/user/profile", function (Request $request) {
        $user = $request->user();
        return response()->json([
            "code" => 200,
            "message" => "success",
            "data" => [
                "id" => $user->id,
                "name" => $user->name,
                "openid" => $user->openid,
                "points" => $user->points,
                "volunteer_hours" => $user->volunteer_hours,
                "is_captain" => $user->is_captain,
                "created_at" => $user->created_at,
            ],
        ]);
    });
});

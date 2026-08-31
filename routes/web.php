<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// 微信小程序静默登录（已在 VerifyCsrfToken 排除 CSRF）
Route::post('/wechat/login', [AuthController::class, 'wechatLogin']);

Route::middleware('wechat.proxy.auth')->group(function () {
    Route::get('/h5/profile', function () {
        $user = Auth::user();
        return response()->json([
            'name' => $user->name,
            'openid' => $user->openid,
            'points' => $user->points,
        ]);
    });
});

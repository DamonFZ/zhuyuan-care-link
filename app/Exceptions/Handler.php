<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // ===== API 统一异常响应 { code, message, data } =====
        // 仅作用于 API / JSON 请求（Accept: application/json 或路径 /api/*），
        // 不影响 Filament 后台与居民端 Web 视图。
        // 注意：小程序 utils/request.js 的 401 处理基于 HTTP statusCode，
        // 这里仅把响应体统一为 {code,message,data}，HTTP 状态码保持不变，因此不破坏既有逻辑。

        // 401：未认证 / Token 失效（Sanctum auth:sanctum 中间件抛出）
        $this->renderable(function (AuthenticationException $e, Request $request) {
            if ($this->wantsJson($request)) {
                return $this->jsonError(401, '未登录或登录已失效，请重新登录');
            }
        });

        // 404：路由不存在 / 模型未找到（findOrFail、路由模型绑定等）
        // 控制器内手动返回的 404 响应不走这里（直接透传，保留更具体的业务文案）。
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($this->wantsJson($request)) {
                return $this->jsonError(404, '请求的资源不存在');
            }
        });

        // 其余 HTTP 异常（如 405 Method Not Allowed、429 Too Many Requests 等）统一格式
        $this->renderable(function (HttpExceptionInterface $e, Request $request) {
            if ($this->wantsJson($request)) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: '请求失败';
                // 5xx 不向外暴露原始异常信息
                if ($status >= 500) {
                    $message = '服务器内部错误';
                }
                return $this->jsonError($status, $message);
            }
        });
    }

    /**
     * 判断是否为 API / JSON 请求（需要统一 JSON 错误响应）
     */
    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    /**
     * 统一错误响应结构
     */
    private function jsonError(int $code, string $message, $data = null)
    {
        return response()->json([
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }
}

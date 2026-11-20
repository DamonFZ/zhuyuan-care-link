<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * API 通用 FormRequest 基类
 *
 * 统一校验失败响应为项目约定的 { code, message, data } 结构
 * （HTTP 422，data 为字段错误明细，message 取第一条错误），
 * 与现有控制器内 Validator::make 的手动返回保持一致。
 */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 鉴权交给路由中间件（auth:sanctum）与控制器，这里默认放行
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            "code"    => 422,
            "message" => $validator->errors()->first(),
            "data"    => $validator->errors(),
        ], 422));
    }
}

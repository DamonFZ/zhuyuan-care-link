<?php

namespace App\Http\Requests\Api;

class LoginRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            // 支持 email 或 account（账号）二选一登录
            "email"    => "required_without:account|nullable|string|email",
            "account"  => "required_without:email|nullable|string",
            "password" => "required|string",
        ];
    }

    public function messages(): array
    {
        return [
            "email.required_without"    => "请输入登录邮箱或账号",
            "account.required_without"  => "请输入登录邮箱或账号",
            "password.required"         => "请输入密码",
        ];
    }
}

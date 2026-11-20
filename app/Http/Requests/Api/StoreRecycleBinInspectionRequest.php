<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StoreRecycleBinInspectionRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        // 箱号做基础 trim（去除首尾空白）
        if ($this->has("bin_code") && is_string($this->input("bin_code"))) {
            $this->merge(["bin_code" => trim($this->input("bin_code"))]);
        }
    }

    public function rules(): array
    {
        return [
            // 回收箱编号：可选，如 ZS0001
            "bin_code"   => "nullable|string|max:50",
            // 状态：可选，未传默认 good
            "status"     => ["nullable", "string", Rule::in(array_keys(\App\Models\RecycleBinInspection::STATUS_MAP))],
            // 照片：必填，1~9 张，元素为上传返回的相对路径字符串
            "images"     => "required|array|min:1|max:9",
            "images.*"   => "required|string",
            // 纬度：可选，定位失败可传 null 或不传
            "latitude"   => "nullable|numeric|between:-90,90",
            // 经度：可选
            "longitude"  => "nullable|numeric|between:-180,180",
        ];
    }

    public function messages(): array
    {
        return [
            "images.required"   => "请至少上传一张巡检照片",
            "images.array"      => "照片参数格式错误",
            "images.min"        => "请至少上传一张巡检照片",
            "images.max"        => "最多上传 9 张照片",
            "images.*.required" => "照片路径不能为空",
            "images.*.string"   => "照片路径必须为字符串",
            "status.in"         => "状态值不合法",
            "latitude.between"  => "纬度取值范围为 -90 ~ 90",
            "longitude.between" => "经度取值范围为 -180 ~ 180",
        ];
    }
}

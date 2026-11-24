<?php

namespace App\Http\Requests\Api;

class StoreRecycleRecordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            // 现场照片：必填，数组 1~30 张，元素为字符串（上传接口返回的相对 path 或完整 URL）
            "image_urls"   => "required|array|min:1|max:" . \App\Models\RecycleRecord::MAX_IMAGES,
            "image_urls.*" => "required|string",

            // 实际重量：数字且 > 0
            "weight" => "required|numeric|gt:0",

            // 金额（元）：选填，数字且 >= 0
            "amount" => "nullable|numeric|min:0",

            // 备注：选填，最长 500 字
            "remark" => "nullable|string|max:500",

            // 旧字段兼容：可空，不再强制前端传；若传则原样写入（向后兼容历史 App）
            "clothes_image" => "nullable|string|max:500",
            "scale_image"   => "nullable|string|max:500",
        ];
    }

    public function messages(): array
    {
        return [
            "image_urls.required"  => "请至少上传一张现场照片",
            "image_urls.array"     => "图片参数格式不正确",
            "image_urls.min"       => "请至少上传一张现场照片",
            "image_urls.max"       => "现场照片最多 " . \App\Models\RecycleRecord::MAX_IMAGES . " 张",
            "image_urls.*.string"  => "图片路径格式不正确",
            "weight.required"      => "请输入实际重量",
            "weight.numeric"       => "重量必须为数字",
            "weight.gt"            => "重量必须大于 0",
            "amount.numeric"       => "金额必须为数字",
            "amount.min"           => "金额不能为负数",
            "remark.max"           => "备注不能超过 500 字",
        ];
    }
}

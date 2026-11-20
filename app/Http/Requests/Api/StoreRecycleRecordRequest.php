<?php

namespace App\Http\Requests\Api;

class StoreRecycleRecordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            // 衣物图片：可传相对路径（/api/upload 返回的 path）或完整 URL
            "clothes_image" => "required|string|max:500",
            // 秤重图片
            "scale_image"   => "required|string|max:500",
            // 实际重量：数字且 > 0
            "weight"        => "required|numeric|gt:0",
        ];
    }

    public function messages(): array
    {
        return [
            "clothes_image.required" => "请上传衣物照片",
            "scale_image.required"   => "请上传秤重照片",
            "weight.required"        => "请输入实际重量",
            "weight.numeric"         => "重量必须为数字",
            "weight.gt"              => "重量必须大于 0",
        ];
    }
}

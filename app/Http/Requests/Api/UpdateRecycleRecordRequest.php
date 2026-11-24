<?php

namespace App\Http\Requests\Api;

/**
 * 编辑回收记录校验（PUT/PATCH /api/recycle-records/{id}）
 *
 * 校验规则与提交接口（StoreRecycleRecordRequest）保持一致：
 * 通过继承复用同一套 rules() / messages()，避免新增/修改字段时两端校验口径漂移。
 */
class UpdateRecycleRecordRequest extends StoreRecycleRecordRequest
{
    // 继承父类的 rules() 与 messages()，暂不单独定制
}

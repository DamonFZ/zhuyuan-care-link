<?php

namespace App\Observers;

use App\Models\VolunteerRecord;
use Illuminate\Support\Facades\DB;

/**
 * 后端兜底观察者（红线：不管前端传入的 multiplier/final_hours 是多少，
 * 一定严格根据用户真实 volunteer_level_id 重算后入库，防篡改）
 */
class VolunteerRecordObserver
{
    public function creating(VolunteerRecord $record): void
    {
        $this->recomputeByRealUserLevel($record);
    }

    public function updating(VolunteerRecord $record): void
    {
        $this->recomputeByRealUserLevel($record);
    }

    public function created(VolunteerRecord $record): void
    {
        // 自动累加志愿时长到用户（行锁 + 小数精度）
        $this->syncUserVolunteerHours($record);
    }

    public function updated(VolunteerRecord $record): void
    {
        // 更新后按差额校准用户总时长：先按 original 减，再加 final
        $this->recalibrateUserHoursAfterUpdate($record);
    }

    public function deleted(VolunteerRecord $record): void
    {
        if (!$record->user_id || !$record->final_hours) {
            return;
        }
        DB::transaction(function () use ($record) {
            $locked = \App\Models\User::where("id", $record->user_id)->lockForUpdate()->first();
            if (!$locked) {
                return;
            }
            $newTotal = bcsub($locked->volunteer_hours, $record->final_hours, 1);
            if (bccomp($newTotal, "0.0", 1) < 0) {
                $newTotal = "0.0";
            }
            $locked->update(["volunteer_hours" => $newTotal]);
        });
    }

    private function recomputeByRealUserLevel(VolunteerRecord $record): void
    {
        if (!$record->user_id || !is_numeric($record->base_hours ?? null)) {
            return;
        }

        // 用行锁查用户真实等级（保证数据一致性）
        $user = DB::transaction(function () use ($record) {
            return \App\Models\User::with("volunteerLevel")
                ->where("id", $record->user_id)
                ->lockForUpdate()
                ->first();
        });
        if (!$user) {
            return;
        }
        $mult = (string)($user->volunteerLevel?->multiplier ?? "1.00");

        $base = number_format((float)$record->base_hours, 1, ".", "");
        $final = number_format(round((float)$base * (float)$mult, 1), 1, ".", "");

        $record->multiplier  = $mult;
        $record->final_hours = $final;
    }

    private function syncUserVolunteerHours(VolunteerRecord $record): void
    {
        if (!$record->user_id || !$record->final_hours) {
            return;
        }
        DB::transaction(function () use ($record) {
            $locked = \App\Models\User::where("id", $record->user_id)->lockForUpdate()->first();
            if (!$locked) {
                return;
            }
            $newTotal = bcadd($locked->volunteer_hours, $record->final_hours, 1);
            $locked->update(["volunteer_hours" => $newTotal]);
        });
    }

    private function recalibrateUserHoursAfterUpdate(VolunteerRecord $record): void
    {
        if (!$record->user_id) {
            return;
        }
        $oldFinal = (string)($record->getOriginal("final_hours") ?? "0");
        $newFinal = (string)($record->final_hours ?? "0");
        $oldUserId = $record->getOriginal("user_id");

        // 用户换绑 → 两边都调整
        if ($oldUserId && $oldUserId != $record->user_id) {
            DB::transaction(function () use ($record, $oldFinal, $oldUserId) {
                $old = \App\Models\User::where("id", $oldUserId)->lockForUpdate()->first();
                if ($old) {
                    $v = bcsub($old->volunteer_hours, $oldFinal, 1);
                    $old->update(["volunteer_hours" => bccomp($v, "0", 1) < 0 ? "0.0" : $v]);
                }
                $new = \App\Models\User::where("id", $record->user_id)->lockForUpdate()->first();
                if ($new) {
                    $new->update(["volunteer_hours" => bcadd($new->volunteer_hours, $record->final_hours, 1)]);
                }
            });
            return;
        }

        $delta = bcsub($newFinal, $oldFinal, 1);
        if (bccomp($delta, "0", 1) === 0) {
            return;
        }
        DB::transaction(function () use ($record, $delta) {
            $locked = \App\Models\User::where("id", $record->user_id)->lockForUpdate()->first();
            if (!$locked) {
                return;
            }
            $newTotal = bcadd($locked->volunteer_hours, $delta, 1);
            if (bccomp($newTotal, "0", 1) < 0) {
                $newTotal = "0.0";
            }
            $locked->update(["volunteer_hours" => $newTotal]);
        });
    }
}

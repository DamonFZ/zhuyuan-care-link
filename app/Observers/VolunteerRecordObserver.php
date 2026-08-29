<?php

namespace App\Observers;

use App\Models\VolunteerRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 后端兜底红线（防前端篡改）+ 资产流水闭环
 *
 *  creating/updating:
 *      - multiplier / final_hours / reward_points 都按用户真实等级 + 岗位真实时薪重算
 *
 *  created:
 *      - 用户总志愿时长 +final_hours
 *      - 用户消费金 +reward_points（通过 modifyPoints，自动记流水）
 *
 *  updated:
 *      - 时长：按 (new_final - old_final) 差额校准
 *      - 消费金：按 (new_reward - old_reward) 差额 modifyPoints（负则扣）
 *
 *  deleted:
 *      - 时长 -final_hours
 *      - 消费金 -reward_points（modifyPoints 负数扣）
 */
class VolunteerRecordObserver
{
    public function creating(VolunteerRecord $record): void
    {
        $this->recomputeAllByTruth($record);
    }

    public function updating(VolunteerRecord $record): void
    {
        $this->recomputeAllByTruth($record);
    }

    public function created(VolunteerRecord $record): void
    {
        if (!$record->user_id) {
            return;
        }

        DB::transaction(function () use ($record) {
            $locked = User::where('id', $record->user_id)->lockForUpdate()->first();
            if (!$locked) return;

            // 1) 累计总志愿时长
            if ($record->final_hours > 0) {
                $locked->update([
                    'volunteer_hours' => bcadd($locked->volunteer_hours, $record->final_hours, 1),
                ]);
            }

            // 2) 累计消费金（通过 modifyPoints，保证流水与余额一致）
            $reward = number_format((float)($record->reward_points ?? 0), 2, '.', '');
            if (bccomp($reward, '0.00', 2) > 0) {
                $locked->refresh();
                $locked->modifyPoints($reward, "志愿服务奖励：{$record->title}");
            }
        });
    }

    public function updated(VolunteerRecord $record): void
    {
        if (!$record->user_id) return;

        $oldFinal   = (string)($record->getOriginal('final_hours')   ?? '0');
        $newFinal   = (string)($record->final_hours                ?? '0');
        $oldReward  = (string)($record->getOriginal('reward_points') ?? '0.00');
        $newReward  = (string)($record->reward_points              ?? '0.00');
        $oldUserId  = $record->getOriginal('user_id');

        // 换绑用户：两边各自调整
        if ($oldUserId && $oldUserId != $record->user_id) {
            DB::transaction(function () use ($record, $oldFinal, $oldReward, $oldUserId) {
                // 老用户：扣时长、扣消费金
                $old = User::where('id', $oldUserId)->lockForUpdate()->first();
                if ($old) {
                    $newHours = bcsub($old->volunteer_hours, $oldFinal, 1);
                    $old->update(['volunteer_hours' => bccomp($newHours, '0', 1) < 0 ? '0.0' : $newHours]);
                    if (bccomp($oldReward, '0.00', 2) > 0) {
                        $old->refresh()->modifyPoints(bcmul($oldReward, '-1', 2),
                            "志愿服务奖励冲回（调账）：{$record->title}");
                    }
                }
                // 新用户：加时长、加消费金
                $newU = User::where('id', $record->user_id)->lockForUpdate()->first();
                if ($newU) {
                    $newU->update([
                        'volunteer_hours' => bcadd($newU->volunteer_hours, $record->final_hours, 1),
                    ]);
                    if (bccomp($record->reward_points, '0.00', 2) > 0) {
                        $newU->refresh()->modifyPoints($record->reward_points,
                            "志愿服务奖励（承接调账）：{$record->title}");
                    }
                }
            });
            return;
        }

        $deltaH  = bcsub($newFinal,  $oldFinal,  1);
        $deltaR  = bcsub($newReward, $oldReward, 2);

        DB::transaction(function () use ($record, $deltaH, $deltaR) {
            $locked = User::where('id', $record->user_id)->lockForUpdate()->first();
            if (!$locked) return;

            if (bccomp($deltaH, '0', 1) !== 0) {
                $newHours = bcadd($locked->volunteer_hours, $deltaH, 1);
                $locked->update(['volunteer_hours' => bccomp($newHours, '0', 1) < 0 ? '0.0' : $newHours]);
                $locked->refresh();
            }

            if (bccomp($deltaR, '0.00', 2) !== 0) {
                $event = (bccomp($deltaR, '0.00', 2) > 0 ? "志愿服务奖励调增" : "志愿服务奖励扣回") . "：{$record->title}";
                // 直接传递带符号的差值；modifyPoints 内部支持正数增加、负数扣除
                $locked->modifyPoints($deltaR, $event);
            }
        });
    }

    public function deleted(VolunteerRecord $record): void
    {
        if (!$record->user_id) return;

        DB::transaction(function () use ($record) {
            $locked = User::where('id', $record->user_id)->lockForUpdate()->first();
            if (!$locked) return;

            if ($record->final_hours > 0) {
                $newHours = bcsub($locked->volunteer_hours, $record->final_hours, 1);
                $locked->update(['volunteer_hours' => bccomp($newHours, '0', 1) < 0 ? '0.0' : $newHours]);
                $locked->refresh();
            }

            $reward = (string)($record->reward_points ?? '0.00');
            if (bccomp($reward, '0.00', 2) > 0) {
                $locked->modifyPoints(bcmul($reward, '-1', 2),
                    "志愿服务奖励冲回（记录删除）：{$record->title}");
            }
        });
    }

    /**
     * 按真实数据严格重算
     * multiplier    ← user.volunteerLevel.multiplier
     * final_hours   ← base_hours × multiplier
     * reward_points ← base_hours × volunteerServiceType.base_reward_rate × multiplier
     */
    private function recomputeAllByTruth(VolunteerRecord $record): void
    {
        if (!$record->user_id || !is_numeric($record->base_hours ?? null)) {
            return;
        }

        DB::transaction(function () use ($record) {
            $user = User::with('volunteerLevel')
                ->where('id', $record->user_id)
                ->lockForUpdate()
                ->first();
            if (!$user) return;

            $mult = (string)($user->volunteerLevel?->multiplier ?? '1.00');
            $rate = '0.00';
            if ($record->volunteer_service_type_id) {
                $type = DB::table('volunteer_service_types')
                    ->where('id', $record->volunteer_service_type_id)
                    ->lock('LOCK IN SHARE MODE')
                    ->first();
                $rate = (string)($type?->base_reward_rate ?? '0.00');
            }

            $base = number_format((float)$record->base_hours, 1, '.', '');
            $finalH = number_format(round((float)$base * (float)$mult, 1), 1, '.', '');
            $reward = number_format(round((float)$base * (float)$rate * (float)$mult, 2), 2, '.', '');

            $record->multiplier      = $mult;
            $record->final_hours     = $finalH;
            $record->reward_points   = $reward;
        });
    }
}
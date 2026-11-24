<?php

namespace Tests\Feature;

use App\Models\RecycleRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * 回收记录「编辑 / 删除」接口测试
 *
 * 覆盖关键场景：
 *   1. 本人编辑成功（PUT）
 *   2. 他人编辑 → 403
 *   3. 24 小时内删除成功（DELETE）
 *   4. 满 24 小时删除 → 403 拦截
 *   5. 记录不存在 → 404（update / delete 均覆盖）
 * 附加：未登录 401、校验失败 422、PATCH 与 PUT 等价、审计日志落库。
 *
 * 运行前提：本机需安装 PHP 8.1+ 并配置好测试数据库。
 *   - 默认使用 .env 的数据库连接；如需内存库可在 phpunit.xml 中放开
 *     DB_CONNECTION=sqlite / DB_DATABASE=:memory: 两行注释。
 *   - 执行：php artisan test --filter=RecycleRecordCrudTest
 */
class RecycleRecordCrudTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecycler(array $attrs = []): User
    {
        return User::factory()->create(array_merge(
            ['role' => User::ROLE_RECYCLER],
            $attrs
        ));
    }

    private function makeRecord(User $owner, array $attrs = []): RecycleRecord
    {
        return RecycleRecord::create(array_merge([
            'user_id'    => $owner->id,
            'image_urls' => ['recycle-records/a.jpg'],
            'weight'     => 5.5,
            'amount'     => 10.00,
            'remark'     => '原始备注',
        ], $attrs));
    }

    /** 1. 本人编辑成功（PUT）：字段更新 + 返回完整记录 + 审计日志记录 updated */
    public function test_owner_can_update_own_record(): void
    {
        $user = $this->makeRecycler();
        $record = $this->makeRecord($user);
        Sanctum::actingAs($user);

        $payload = [
            'image_urls' => ['recycle-records/b.jpg', 'recycle-records/c.jpg'],
            'weight'     => 8.0,
            'amount'     => null,
            'remark'     => '更新后的备注',
        ];

        $response = $this->putJson("/api/recycle-records/{$record->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', '回收记录更新成功')
            ->assertJsonPath('data.remark', '更新后的备注')
            ->assertJsonPath('data.amount', null)
            ->assertJsonPath('data.image_urls.0', 'recycle-records/b.jpg')
            ->assertJsonPath('data.image_urls.1', 'recycle-records/c.jpg')
            ->assertJsonStructure(['data' => ['full_image_urls', 'created_at', 'user']]);

        $record->refresh();
        $this->assertEqualsWithDelta(8.0, (float) $record->weight, 0.001);
        $this->assertSame('更新后的备注', $record->remark);
        $this->assertNull($record->amount);

        // 审计日志：updated 事件已落库，且仅记录变更字段（logOnlyDirty）
        $this->assertTrue(
            Activity::forSubject($record)->where('event', 'updated')->exists()
        );
    }

    /** 2. 他人编辑 → 403 无权操作他人的回收记录 */
    public function test_non_owner_cannot_update_record(): void
    {
        $owner = $this->makeRecycler();
        $other = $this->makeRecycler();
        $record = $this->makeRecord($owner);
        Sanctum::actingAs($other);

        $response = $this->putJson("/api/recycle-records/{$record->id}", [
            'image_urls' => ['recycle-records/x.jpg'],
            'weight'     => 1.0,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 403)
            ->assertJsonPath('message', '无权操作他人的回收记录');

        // 数据未被篡改
        $record->refresh();
        $this->assertSame(['recycle-records/a.jpg'], $record->image_urls);
    }

    /** 3. 24 小时内删除成功：返回 200、记录被硬删除、审计日志记录 deleted */
    public function test_owner_can_delete_within_24_hours(): void
    {
        $user = $this->makeRecycler();
        $record = $this->makeRecord($user); // created_at = now，未满 24h
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/recycle-records/{$record->id}");

        $response->assertStatus(200)
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', '删除成功')
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('recycle_records', ['id' => $record->id]);

        // 审计日志：deleted 事件已落库（subject 被硬删除后仍可按 subject_id 查询）
        $this->assertTrue(
            Activity::forSubject($record)->where('event', 'deleted')->exists()
        );
    }

    /** 4. 满 24 小时删除 → 403 拦截，记录保留 */
    public function test_cannot_delete_after_24_hours(): void
    {
        $user = $this->makeRecycler();
        $record = $this->makeRecord($user);
        // 把创建时间回拨到 25 小时前（>= 24h）
        $record->forceFill(['created_at' => now()->subHours(25)])->save();
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/recycle-records/{$record->id}");

        $response->assertStatus(403)
            ->assertJsonPath('code', 403)
            ->assertJsonPath('message', '超过24小时的记录不能删除');

        $this->assertDatabaseHas('recycle_records', ['id' => $record->id]);
    }

    /** 5a. 记录不存在 → 编辑返回 404 */
    public function test_update_missing_record_returns_404(): void
    {
        Sanctum::actingAs($this->makeRecycler());

        $this->putJson('/api/recycle-records/999999', [
            'image_urls' => ['x.jpg'],
            'weight'     => 1,
        ])
            ->assertStatus(404)
            ->assertJsonPath('code', 404)
            ->assertJsonPath('message', '回收记录不存在');
    }

    /** 5b. 记录不存在 → 删除返回 404 */
    public function test_delete_missing_record_returns_404(): void
    {
        Sanctum::actingAs($this->makeRecycler());

        $this->deleteJson('/api/recycle-records/999999')
            ->assertStatus(404)
            ->assertJsonPath('code', 404)
            ->assertJsonPath('message', '回收记录不存在');
    }

    /** 附加：未登录访问 → 401 */
    public function test_guest_cannot_update_or_delete(): void
    {
        $this->putJson('/api/recycle-records/1', [])->assertStatus(401);
        $this->deleteJson('/api/recycle-records/1')->assertStatus(401);
    }

    /** 附加：校验失败 → 422（与提交接口同一套规则） */
    public function test_update_validation_fails_with_422(): void
    {
        $user = $this->makeRecycler();
        $record = $this->makeRecord($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/recycle-records/{$record->id}", [
            'image_urls' => [],   // min:1 失败
            'weight'     => 0,    // gt:0 失败
            'amount'     => -1,   // min:0 失败
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    /** 附加：PATCH 与 PUT 等价，均可更新 */
    public function test_patch_also_updates_record(): void
    {
        $user = $this->makeRecycler();
        $record = $this->makeRecord($user);
        Sanctum::actingAs($user);

        $this->patchJson("/api/recycle-records/{$record->id}", [
            'image_urls' => ['recycle-records/p.jpg'],
            'weight'     => 3.0,
        ])
            ->assertStatus(200)
            ->assertJsonPath('code', 200);
    }
}

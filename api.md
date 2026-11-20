# 内部回收员工 RESTful API 接口文档与 Postman 测试用例

> 适用范围：供 Flutter 安卓端对接的「机构内部回收员工」业务接口。
> 业务隔离：本套接口仅操作 `recycle_records` 表与 `RecycleRecord` 模型，与居民回收（`recycling_orders` / `/api/recycle/reserve`）完全独立，互不影响。
> 技术栈：PHP 8.1 + Laravel 10.x + Sanctum Token。

---

## 目录

1. [通用约定](#1-通用约定)
2. [测试账号](#2-测试账号)
3. [接口清单](#3-接口清单)
4. [Postman 测试用例](#4-postman-测试用例)
5. [Postman 快捷操作建议](#5-postman-快捷操作建议)
6. [回归测试记录](#6-回归测试记录)

---

## 1. 通用约定

### 1.1 Base URL

| 访问方式 | Base URL |
|---|---|
| 容器内 / 已配 hosts | `http://carelink.damon.com/api` |
| 宿主机直连 | 容器已映射 `0.0.0.0:80→80`，在宿主机 `/etc/hosts` 加 `127.0.0.1 carelink.damon.com` 后使用同上地址 |

> ⚠️ 所有请求建议带 `Accept: application/json`，否则未认证时框架可能返回 HTML 异常页而非 JSON。

### 1.2 鉴权方式

除登录接口外，其余接口均需在 Header 携带 Sanctum Token：

```
Authorization: Bearer <token>
Accept: application/json
```

### 1.3 统一响应格式

```json
{
  "code": <HTTP 状态码>,
  "message": "<提示文案>",
  "data": <业务数据或 null>
}
```

- `code` 与 HTTP 状态码一致：成功 `200`（创建为 `201`）；失败为 `400/401/403/404/422` 等。
- `422` 校验失败时，`data` 为字段错误明细对象，`message` 取第一条错误。
- 全局异常已统一：`401`（未登录/Token 失效）、`404`（资源不存在/路由不存在）及其他 HTTP 异常均返回上述结构（仅作用于 API/JSON 请求，不影响 Filament 后台与居民小程序）。

### 1.4 Postman 环境变量（推荐）

| 变量名 | 说明 | 初始值 |
|---|---|---|
| `baseUrl` | 接口前缀 | `http://carelink.damon.com/api` |
| `token` | 当前默认身份 token（登录后自动写入） | 空 |
| `recyclerToken` | 回收员 token | 空 |
| `operatorToken` | 操作员 token | 空 |
| `clothesPath` | 上传返回的衣物图相对路径 | 空 |
| `scalePath` | 上传返回的秤图相对路径 | 空 |
| `recordId` | 新提交记录的 id | 空 |

---

## 2. 测试账号

> 已预置供联调使用，**上线前请删除或修改密码**。

| 角色 | 邮箱 | 密码 | 说明 |
|---|---|---|---|
| 回收员 `recycler` | `recycler@test.com` | `secret123` | 只能查看/提交自己的记录 |
| 操作员 `operator` | `operator@test.com` | `secret123` | 可查看全部记录 |

> 居民用户 `password` 为 NULL，走 `/api/wechat/login`，无法通过 `/api/login` 登录——天然业务隔离。

---

## 3. 接口清单

| # | 方法 | 路径 | 鉴权 | 说明 |
|---|---|---|---|---|
| 1 | `POST` | `/api/login` | 否 | 员工账号密码登录，签发 Sanctum Token |
| 2 | `POST` | `/api/logout` | 是 | 撤销当前请求使用的 Token |
| 3 | `GET` | `/api/user` | 是 | 当前登录用户基本信息 |
| 4 | `POST` | `/api/upload` | 是 | 单张图片上传，返回 `path` 与完整 `url` |
| 5 | `POST` | `/api/recycle-records` | 是 | 提交一条回收记录 |
| 6 | `GET` | `/api/recycle-records` | 是 | 历史记录分页列表 + 汇总重量统计 |
| 7 | `GET` | `/api/recycle-records/{id}` | 是 | 单条回收记录详情 |

---

## 4. Postman 测试用例

### 用例 1：员工登录（正向 + 负向）

**请求**

| 项 | 值 |
|---|---|
| Method | `POST` |
| URL | `{{baseUrl}}/login` |
| Headers | `Content-Type: application/json`、`Accept: application/json` |
| Body | raw → JSON |

请求体：

```json
{
  "email": "recycler@test.com",
  "password": "secret123"
}
```

**Tests 标签页**（断言 + 自动保存 token）

```javascript
const json = pm.response.json();
pm.test("登录成功 code=200", () => pm.expect(json.code).to.eql(200));
pm.test("返回 token 与 user", () => {
  pm.expect(json.data.token).to.be.a("string");
  pm.expect(json.data.user).to.have.property("role");
  pm.expect(json.data.user).to.have.property("role_text");
});
pm.environment.set("recyclerToken", json.data.token);
pm.environment.set("token", json.data.token); // 后续请求默认用回收员身份
```

**预期响应（200）**

```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "106|xxxxxxxxxxxxxxxx",
    "user": {
      "id": 10,
      "name": "张回收",
      "avatar": null,
      "email": "recycler@test.com",
      "role": "recycler",
      "role_text": "回收员",
      "created_at": "2026-11-20 02:56:11"
    }
  }
}
```

**负向子用例**（同 URL，修改 Body）

| 子用例 | Body | 预期 HTTP | 预期 `code` / `message` |
|---|---|---|---|
| 密码错误 | `{"email":"recycler@test.com","password":"wrong"}` | 401 | `401` / `账号或密码错误` |
| 缺密码 | `{"email":"recycler@test.com"}` | 422 | `422` / `请输入密码`，`data` 含字段错误 |
| 缺账号 | `{"password":"secret123"}` | 422 | `422` / `请输入登录邮箱或账号` |
| 用 account 字段登录 | `{"account":"operator@test.com","password":"secret123"}` | 200 | 验证 email/account 二选一可用 |
| 居民账号无法登录 | `{"email":"resident@test.com","password":"任意"}` | 401 | 居民 password 为 NULL，无法通过此接口登录 |

> 建议额外建一个请求登录 `operator@test.com`，在其 Tests 里 `pm.environment.set("operatorToken", json.data.token)`。

---

### 用例 2：获取当前用户信息

**请求**

| 项 | 值 |
|---|---|
| Method | `GET` |
| URL | `{{baseUrl}}/user` |
| Headers | `Accept: application/json`、`Authorization: Bearer {{token}}` |
| Body | 无 |

**Tests**

```javascript
const json = pm.response.json();
pm.test("200 且含角色信息", () => {
  pm.expect(json.code).to.eql(200);
  pm.expect(json.data).to.have.property("role");
  pm.expect(json.data).to.have.property("role_text");
});
```

**预期响应（200）**：`data` 含 `id / name / avatar / email / role / role_text / created_at`。

---

### 用例 3：退出登录

**请求**

| 项 | 值 |
|---|---|
| Method | `POST` |
| URL | `{{baseUrl}}/logout` |
| Headers | `Accept: application/json`、`Authorization: Bearer {{token}}` |

**预期响应（200）**

```json
{ "code": 200, "message": "已退出登录", "data": null }
```

**验证 Token 已失效**：退出后用同一个 token 再请求 `GET {{baseUrl}}/user`，应返回：

```json
{ "code": 401, "message": "未登录或登录已失效，请重新登录", "data": null }
```

---

### 用例 4：图片上传（正向 + 负向）

**请求**

| 项 | 值 |
|---|---|
| Method | `POST` |
| URL | `{{baseUrl}}/upload` |
| Headers | `Accept: application/json`、`Authorization: Bearer {{token}}` |
| Body | `form-data` |

form-data 字段：

| KEY | 类型 | VALUE | 说明 |
|---|---|---|---|
| `file` | **File** | 选择本地 jpg/png/webp（≤5MB） | ⚠️ 必须选 File 类型 |
| `dir` | Text | `recycle-records` | 不传默认 `avatars` |

> 字段名也支持 `image` / `avatar` / `media`，推荐统一用 `file`。

**Tests**

```javascript
const json = pm.response.json();
pm.test("上传成功 200", () => pm.expect(json.code).to.eql(200));
pm.test("返回 path 与可访问 url", () => {
  pm.expect(json.data.path).to.be.a("string");
  pm.expect(json.data.url).to.include("storage/recycle-records/");
});
pm.environment.set("clothesPath", json.data.path); // 存给提交记录用
```

**预期响应（200）**

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "url": "http://carelink.damon.com/storage/recycle-records/xxx.png",
    "path": "recycle-records/xxx.png",
    "relativePath": "recycle-records/xxx.png",
    "dir": "recycle-records",
    "size": 1234,
    "mime": "image/png",
    "userId": 10
  }
}
```

> 提交记录时回传 `path` 字段值（`clothes_image` / `scale_image`），框架会自动拼接成完整 URL。

**负向子用例**

| 子用例 | 操作 | 预期 HTTP | 预期 `code` / `message` |
|---|---|---|---|
| 非法目录 | `dir` 填 `../etc` | 400 | `400` / `不支持的存储目录` |
| 非图片文件 | 上传 `.txt` | 400 | `400` / `不支持的文件类型` |
| 超 5MB | 上传 >5MB 图片 | 400 | `400` / `图片不得超过 5MB` |
| 不传文件 | Body 只留 `dir` | 400 | `400` / `未收到有效文件` |
| 居民传回收目录 | 用居民 token + `dir=recycle-records` | 403 | `403` / `无权上传回收业务图片` |

> 建议再执行一次上传（选另一张图），把返回 `path` 存到环境变量 `scalePath`，供提交记录使用。

---

### 用例 5：提交回收记录（正向 + 负向）

前置：已通过用例 4 拿到 `{{clothesPath}}` 与 `{{scalePath}}`。

**请求**

| 项 | 值 |
|---|---|
| Method | `POST` |
| URL | `{{baseUrl}}/recycle-records` |
| Headers | `Accept: application/json`、`Content-Type: application/json`、`Authorization: Bearer {{token}}` |
| Body | raw → JSON |

请求体：

```json
{
  "clothes_image": "{{clothesPath}}",
  "scale_image": "{{scalePath}}",
  "weight": 3.5
}
```

**Tests**

```javascript
const json = pm.response.json();
pm.test("创建成功 201", () => pm.expect(json.code).to.eql(201));
pm.test("含完整图片 URL 与回收人员", () => {
  pm.expect(json.data.clothes_image_url).to.be.a("string");
  pm.expect(json.data.scale_image_url).to.be.a("string");
  pm.expect(json.data.user).to.have.property("name");
});
pm.environment.set("recordId", json.data.id); // 存下新记录 id 给详情用例
```

**预期响应（201）**

```json
{
  "code": 201,
  "message": "回收记录提交成功",
  "data": {
    "id": 4,
    "weight": 3.5,
    "created_at": "2026-11-20 03:08:41",
    "date": "2026-11-20",
    "clothes_image": "recycle-records/xxx.png",
    "clothes_image_url": "http://carelink.damon.com/storage/recycle-records/xxx.png",
    "scale_image": "recycle-records/yyy.png",
    "scale_image_url": "http://carelink.damon.com/storage/recycle-records/yyy.png",
    "user": { "id": 10, "name": "张回收", "role": "recycler" }
  }
}
```

**负向子用例**

| 子用例 | Body / Header | 预期 HTTP | 预期 `code` / `message` |
|---|---|---|---|
| 缺衣物图 | 去掉 `clothes_image` | 422 | `422` / `请上传衣物照片` |
| 缺秤图 | 去掉 `scale_image` | 422 | `422` / `请上传秤重照片` |
| 重量为 0 | `"weight": 0` | 422 | `422` / `重量必须大于 0` |
| 重量为负数 | `"weight": -1` | 422 | `422` |
| 重量非数字 | `"weight": "abc"` | 422 | `422` / `重量必须为数字` |
| 缺重量 | 去掉 `weight` | 422 | `422` / `请输入实际重量` |
| 居民提交 | Header 用居民 token | 403 | `403` / `无权提交回收记录` |

---

### 用例 6：历史记录列表 + 汇总统计

**请求**

| 项 | 值 |
|---|---|
| Method | `GET` |
| URL | `{{baseUrl}}/recycle-records` |
| Headers | `Accept: application/json`、`Authorization: Bearer {{token}}` |
| Params | `per_page=15`、`date=2026-11-20`（均可选） |

查询参数说明：

| 参数 | 类型 | 说明 |
|---|---|---|
| `per_page` | int | 每页条数，范围 1–50，默认 15 |
| `date` | string | 可选，格式 `YYYY-MM-DD`；传了则筛选当日并返回 `date_total_weight` |

**Tests**

```javascript
const json = pm.response.json();
pm.test("200", () => pm.expect(json.code).to.eql(200));
pm.test("含汇总统计字段", () => {
  pm.expect(json.data.summary).to.have.property("today_total_weight");
  pm.expect(json.data.summary).to.have.property("total_weight");
});
pm.test("列表按创建时间倒序", () => {
  const items = json.data.items;
  if (items.length > 1) {
    pm.expect(items[0].created_at).to.be.at.least(items[1].created_at);
  }
});
```

**预期响应（200）**

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "items": [
      {
        "id": 4,
        "weight": 3.5,
        "created_at": "2026-11-20 03:08:41",
        "date": "2026-11-20",
        "clothes_image": "recycle-records/xxx.png",
        "clothes_image_url": "http://carelink.damon.com/storage/recycle-records/xxx.png",
        "scale_image": "recycle-records/yyy.png",
        "scale_image_url": "http://carelink.damon.com/storage/recycle-records/yyy.png",
        "user": { "id": 10, "name": "张回收", "role": "recycler" }
      }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 2,
    "last_page": 1,
    "has_more_pages": false,
    "summary": {
      "today_total_weight": 7.0,
      "total_weight": 7.0,
      "date": "2026-11-20",
      "date_total_weight": 7.0
    }
  }
}
```

> `summary.today_total_weight`：今日（可见范围）总重量；`summary.total_weight`：全部（可见范围）总重量。App 历史页顶部统计卡片直接渲染这两个字段。

**权限子用例**

| 子用例 | Header token | 预期 |
|---|---|---|
| 回收员查看 | `{{recyclerToken}}` | `items` 仅含自己的记录 |
| 操作员查看 | `{{operatorToken}}` | `items` 含全部回收员的记录 |
| 居民查看 | 居民 token | `403` / `无权查看回收记录` |

---

### 用例 7：单条回收记录详情（正向 + 负向）

前置：用例 5 存下的 `{{recordId}}`。

**请求**

| 项 | 值 |
|---|---|
| Method | `GET` |
| URL | `{{baseUrl}}/recycle-records/{{recordId}}` |
| Headers | `Accept: application/json`、`Authorization: Bearer {{token}}` |

**Tests**

```javascript
const json = pm.response.json();
pm.test("200 且含完整信息", () => {
  pm.expect(json.code).to.eql(200);
  pm.expect(json.data).to.include.keys(
    "id", "weight", "created_at",
    "clothes_image", "clothes_image_url",
    "scale_image", "scale_image_url", "user"
  );
});
```

**预期响应（200）**：同用例 5 的 `data` 结构，含完整图片访问 URL 与回收人员姓名、角色。

**负向子用例**

| 子用例 | URL / Header | 预期 HTTP | 预期 `code` / `message` |
|---|---|---|---|
| 记录不存在 | `/recycle-records/999999` | 404 | `404` / `回收记录不存在`，**Content-Type 为 application/json** |
| 越权查看他人 | 回收员 token 请求操作员的记录 id | 403 | `403` / `无权查看他人的回收记录` |
| 未知路由 | `/this-route-not-exist` | 404 | `404` / `请求的资源不存在`（Handler 兜底） |

> 「记录不存在」用例请额外断言响应是 JSON 而非 HTML（见下方快捷建议），用于监控 nginx 是否又把 404 拦截成 HTML 页面。

---

## 5. Postman 快捷操作建议

1. **Collection 级预设 Header**：在 Collection → Headers 预设 `Accept: application/json`，子请求自动继承，避免每个请求手填。
2. **鉴权统一配置**：Collection → Authorization 选 `Bearer Token`，Token 填 `{{token}}`，全集合复用。
3. **登录后置脚本自动写 token**：在用例 1 的 Tests 里 `pm.environment.set("token", ...)`，后续请求无需手动粘贴。
4. **统一格式断言**：所有成功用例加 `pm.expect(json.code).to.eql(对应码)`；失败用例断言 `code` 等于错误码且 `data` 为 `null`（422 除外，`data` 是字段错误对象）。
5. **404 必须是 JSON 的监控断言**（在「记录不存在」用例 Tests 中添加）：

   ```javascript
   pm.test("404 响应为 JSON 而非 HTML", () => {
     pm.expect(pm.response.headers.get("Content-Type")).to.include("application/json");
   });
   ```

   若该断言失败（返回 HTML），说明服务器 nginx vhost 的 `error_page 404 /404.html;` 被宝塔面板重新覆盖，需重新注释该行并执行 `nginx -s reload`。

---

## 6. 回归测试记录

> 以下为开发阶段端到端实测结果（容器内 `curl` 验证），Postman 复测时可对照。

| 用例 | 结果 |
|---|---|
| 登录成功 / 密码错 401 / 缺参 422 / account 字段登录 | ✅ 通过 |
| `GET /api/user` 返回当前用户含 role/role_text | ✅ 通过 |
| 上传 `dir=recycle-records` 返回 path+url；非法 dir 400；居民传回收目录 403 | ✅ 通过 |
| 提交记录 201；缺图 422；重量 0/负数/非数字 422；居民提交 403 | ✅ 通过 |
| 列表 + summary 统计（today_total_weight / total_weight）；按 date 筛选 | ✅ 通过 |
| 详情 200；越权 403；不存在记录 404 为 JSON | ✅ 通过 |
| 未知路由 404 为 JSON（Handler 兜底） | ✅ 通过 |
| operator 看全部；recycler 只看自己 | ✅ 通过 |
| 退出 200；退出后旧 token 401 统一 JSON | ✅ 通过 |
| Token 缺失/失效 → 统一 401 JSON（修复后） | ✅ 通过 |
| 居民端 `/api/slides`、`/api/user`、`/api/wechat/login` 422 行为不变；Filament `/admin` 200 HTML | ✅ 无回归 |

**两个已修复的交付阻塞问题**：

- **401 非统一格式**：`app/Exceptions/Handler.php` 注册 `AuthenticationException` renderable，API 请求返回 `{code:401,...}`。居民小程序 `utils/request.js` 基于 HTTP statusCode 处理 401，HTTP 码不变故无回归。
- **404 被 nginx 拦截成 HTML**：Handler 注册 `NotFoundHttpException` renderable（兜底）；同时在服务器 vhost 注释掉 `error_page 404 /404.html;` 并 `nginx -s reload`。该 vhost 改动在仓库外，若宝塔面板重新保存站点可能被覆盖，需重新注释。

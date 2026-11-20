<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /** 允许的存储子目录（白名单，防路径穿越） */
    private const ALLOWED_DIRS = [
        'avatars',          // 居民小程序头像（默认，任意登录用户可用）
        'recycle-records',  // 内部回收记录图片（限回收员/操作员）
        'recycle-bins',     // 回收箱巡检图片（限回收员/操作员）
    ];

    /** 仅限内部员工（回收员/操作员）使用的目录 */
    private const STAFF_ONLY_DIRS = [
        'recycle-records',
        'recycle-bins',
    ];

    /**
     * Sanctum 保护：通用图片上传
     *
     * - 字段名：file / image / avatar / media
     * - 可选 query/form 字段 dir：存储子目录，默认 avatars；
     *   传 recycle-records 时仅回收员/操作员可用，存到 public 磁盘下对应目录。
     * - 仅允许 jpg/jpeg/png/webp/gif，最大 5MB。
     *
     * POST /api/upload
     *
     * 注意：居民端默认不传 dir，行为与之前完全一致（向后兼容）。
     */
    public function uploadImage(Request $request)
    {
        // 0) 目录白名单校验 + 内部目录角色限制
        $dir = $request->input('dir', 'avatars');
        if (!in_array($dir, self::ALLOWED_DIRS, true)) {
            return response()->json([
                'code'    => 400,
                'message' => '不支持的存储目录',
                'data'    => null,
            ], 400);
        }

        if (in_array($dir, self::STAFF_ONLY_DIRS, true)) {
            $user = $request->user();
            if (!$user || !in_array($user->role, [User::ROLE_RECYCLER, User::ROLE_OPERATOR], true)) {
                return response()->json([
                    'code'    => 403,
                    'message' => '无权上传回收业务图片',
                    'data'    => null,
                ], 403);
            }
        }

        // 1) 接收文件：字段名优先 file，其次 image / avatar / media
        /** @var UploadedFile|null $file */
        $file = $request->file('file')
            ?? $request->file('image')
            ?? $request->file('avatar')
            ?? $request->file('media');

        if (!$file || !$file->isValid()) {
            return response()->json([
                'code'    => 400,
                'message' => '未收到有效文件（请使用字段名 file / image / avatar 上传）',
                'data'    => null,
            ], 400);
        }

        // 2) 文件类型与大小限制（图片 + 最大 5MB）
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/jpg'];
        if (!in_array(strtolower($file->getMimeType()), $allowedMimes, true)) {
            return response()->json([
                'code'    => 400,
                'message' => '不支持的文件类型（仅支持 jpg/png/webp/gif）',
                'data'    => null,
            ], 400);
        }
        $maxSizeKB = 5 * 1024; // 5MB
        if ($file->getSize() > $maxSizeKB * 1024) {
            return response()->json([
                'code'    => 400,
                'message' => '图片不得超过 5MB',
                'data'    => null,
            ], 400);
        }

        // 3) 存储（public disk = storage/app/public，经 storage:link 映射到 public/storage）
        $relativePath = $file->store($dir, ['disk' => 'public']);
        if (!$relativePath) {
            return response()->json([
                'code'    => 500,
                'message' => '保存文件失败，请重试',
                'data'    => null,
            ], 500);
        }

        // 4) 返回可访问 URL + 相对路径
        $url = url('storage/' . $relativePath);

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => [
                'url'          => $url,
                'path'         => $relativePath,   // 相对路径，提交记录时回传此字段
                'relativePath' => $relativePath,   // 兼容旧字段
                'dir'          => $dir,
                'size'         => $file->getSize(),
                'mime'         => $file->getMimeType(),
                'userId'       => $request->user()?->id,
            ],
        ], 200);
    }
}

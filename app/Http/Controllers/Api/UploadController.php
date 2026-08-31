<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Sanctum 保护：小程序头像（或其他图片）上传
     * 前端通过 wx.uploadFile 上传字段名 file（formData: 无要求；file 字段本身传二进制）
     * 保存到 storage/app/public/avatars → 通过 public/storage/avatars/xxx 访问
     * POST /api/upload
     */
    public function uploadImage(Request $request)
    {
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
        $dir = 'avatars';
        $relativePath = $file->store($dir, ['disk' => 'public']);
        if (!$relativePath) {
            return response()->json([
                'code'    => 500,
                'message' => '保存文件失败，请重试',
                'data'    => null,
            ], 500);
        }

        // 4) 返回可访问 URL
        $url = url('storage/' . $relativePath);

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => [
                'url'          => $url,
                'relativePath' => $relativePath,
                'size'         => $file->getSize(),
                'mime'         => $file->getMimeType(),
                'userId'       => $request->user()?->id,
            ],
        ], 200);
    }
}

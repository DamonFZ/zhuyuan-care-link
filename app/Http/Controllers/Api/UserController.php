<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Sanctum 保护：查询当前登录用户的完整个人资料
     * GET /api/user/profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('volunteerLevel');

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => $this->serializeUser($user),
        ], 200);
    }

    /**
     * Sanctum 保护：更新当前登录用户的个人信息
     * 请求字段：name (字符串)，avatar (字符串URL：已由 /api/upload 返回的线上地址)
     * POST /api/user/profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'   => 'nullable|string|max:50',
            'avatar' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 422,
                'message' => $validator->errors()->first(),
                'data'    => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $user = $request->user();

        $dirty = [];
        if (isset($validated['name']) && $validated['name'] !== '' && $validated['name'] !== $user->name) {
            $user->name = $validated['name'];
            $dirty[] = 'name';
        }
        if (isset($validated['avatar']) && $validated['avatar'] !== $user->avatar) {
            $user->avatar = $validated['avatar'];
            $dirty[] = 'avatar';
        }

        if (count($dirty) > 0) {
            $user->save();
        }

        $user->loadMissing('volunteerLevel');

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => [
                'updated_fields' => $dirty,
                'user'           => $this->serializeUser($user),
            ],
        ], 200);
    }

    private function serializeUser($user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'avatar'     => $user->avatar ?: null,
            'email'      => $user->email,
            'role'       => $user->role ?: 'resident',
            'points'     => (float) ($user->points ?? 0),
            'volunteer_hours' => (float) ($user->volunteer_hours ?? 0),
            'volunteer_level_id' => $user->volunteer_level_id,
            'volunteerLevel' => $user->volunteerLevel ? [
                'id'         => $user->volunteerLevel->id,
                'name'       => $user->volunteerLevel->name,
                'multiplier' => (float) $user->volunteerLevel->multiplier,
            ] : null,
            'created_at' => (string) $user->created_at,
        ];
    }
}

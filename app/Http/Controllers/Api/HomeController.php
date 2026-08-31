<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slide;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * 公开：获取小程序首页轮播图
     * 按 sort_order ASC，仅返回 is_visible=true
     * GET /api/slides
     */
    public function getSlides(Request $request)
    {
        $slides = Slide::query()
            ->where('is_visible', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (Slide $s) => [
                'id'         => $s->id,
                'title'      => $s->title,
                'image_url'  => $s->image_url,     // 通过 model 访问器拼 public/storage/xxx URL
                'image_path' => $s->image_path,
                'sort_order' => $s->sort_order,
            ])
            ->values();

        return response()->json([
            'code'    => 200,
            'message' => 'success',
            'data'    => $slides,
        ], 200);
    }
}

<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Models\Like;

class LikeController extends BaseController
{
    public function toggle(Request $request, $articleId)
    {
        $articleId = (int) $articleId;
        if (!$articleId) {
            $this->json(['error' => 'Invalid article ID'], 400);
            return;
        }

        $userId = null;
        if ($this->isLoggedIn()) {
            $userId = \LyBlog\Core\Session::getUserId();
        }

        $ip = $request->getIp();
        $liked = Like::toggle($articleId, $userId, $ip);
        $count = Like::getCount($articleId);

        $this->json([
            'liked' => $liked,
            'count' => $count,
        ]);
    }
}

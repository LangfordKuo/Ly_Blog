<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Models\Article;

class SearchController extends BaseController
{
    public function index(Request $request)
    {
        $query = trim($request->getQuery('q', ''));
        $page  = (int) ($request->getQuery('page', 1));

        $data = empty($query) ? ['items' => [], 'total' => 0, 'has_more' => false] : Article::search($query, $page, 10);

        $this->display('search', [
            'query'     => $query,
            'items'     => $data['items'],
            'total'     => $data['total'],
            'has_more'  => $data['has_more'],
            'next_page' => $data['current_page'] + 1,
        ]);
    }
}

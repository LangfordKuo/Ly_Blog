<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Models\Article;
use LyBlog\Models\Tag;

class TagController extends BaseController
{
    public function show(Request $request, $slug = null)
    {
        if (!$slug) { $this->redirect($this->siteUrl()); return; }

        $tag = Tag::findBySlug($slug);
        if (!$tag) { $this->notFound(); return; }

        $page = (int) ($request->getQuery('page', 1));
        $data = Article::getByTag($slug, $page, 10);

        $this->display('tag', [
            'tag'       => $tag,
            'items'     => $data['items'],
            'has_more'  => $data['has_more'],
            'next_page' => $data['current_page'] + 1,
        ]);
    }
}

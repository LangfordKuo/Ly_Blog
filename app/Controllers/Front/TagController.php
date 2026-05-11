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
        $bc = $this->breadcrumb([['首页', $this->siteUrl()], ['标签: ' . $tag['name'], null]]);

        $this->display('tag', [
            'tag'       => $tag,
            'items'     => $data['items'],
            'has_more'   => $data['has_more'],
            'next_page'  => $data['current_page'] + 1,
            'breadcrumb' => $bc,
        ]);
    }
}

<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Models\Article;
use LyBlog\Models\Category;

class CategoryController extends BaseController
{
    public function show(Request $request, $slug = null)
    {
        if (!$slug) { $this->redirect($this->siteUrl()); return; }

        $category = Category::findBySlug($slug);
        if (!$category) { $this->notFound(); return; }

        $page = (int) ($request->getQuery('page', 1));
        $data = Article::getPublished($page, 10, $category['id']);

        $this->display('category', [
            'category'  => $category,
            'items'     => $data['items'],
            'has_more'  => $data['has_more'],
            'next_page' => $data['current_page'] + 1,
        ]);
    }
}

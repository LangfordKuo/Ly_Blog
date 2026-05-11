<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Models\Article;
use LyBlog\Models\Category;
use LyBlog\Models\Tag;
use LyBlog\Models\Comment;

class HomeController extends BaseController
{
    public function index(Request $request)
    {
        $page = (int) ($request->getQuery('page', 1));
        $data = Article::getPublished($page, 10);
        $categories = Category::all('sort_order ASC');
        $tags = Tag::getCloud(20);

        $this->display('home', [
            'items'          => $data['items'],
            'has_more'       => $data['has_more'],
            'next_page'      => $data['current_page'] + 1,
            'categories'     => $categories,
            'tags'           => $tags,
            'popular'        => Article::getPopular(5),
            'article_count'  => Article::count("status = 'published'"),
            'comment_count'  => Comment::count("status = 'approved'"),
            'tag_count'      => Tag::count(),
        ]);
    }
}

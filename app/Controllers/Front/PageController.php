<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Models\Page;

class PageController extends BaseController
{
    public function show(Request $request, $slug = null)
    {
        if (!$slug) { $this->notFound(); return; }

        $page = Page::findBySlug($slug);
        if (!$page || $page['status'] !== 'published') { $this->notFound(); return; }

        $this->display('page', ['page' => $page]);
    }
}

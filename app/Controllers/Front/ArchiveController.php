<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Models\Article;

class ArchiveController extends BaseController
{
    public function index(Request $request, $year = null, $month = null)
    {
        if ($year) {
            $page = (int) ($request->getQuery('page', 1));
            $data = Article::getByArchive($year, $month, $page, 10);

            $this->display('archive', [
                'year'      => $year,
                'month'     => $month,
                'items'     => $data['items'],
                'has_more'  => $data['has_more'],
                'next_page' => $data['current_page'] + 1,
                'archives'  => [],
            ]);
        } else {
            $rawArchives = Article::getArchives();
            $grouped = [];
            foreach ($rawArchives as $a) {
                if (!isset($grouped[$a['year']])) $grouped[$a['year']] = ['year' => $a['year'], 'months' => []];
                $grouped[$a['year']]['months'][] = $a;
            }
            $archives = array_values(array_reverse($grouped));

            $this->display('archive', [
                'year'     => null,
                'month'    => null,
                'items'    => [],
                'has_more' => false,
                'archives' => $archives,
            ]);
        }
    }
}

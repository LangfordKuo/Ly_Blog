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

        // Highlight search terms in results
        if (!empty($query)) {
            $data['items'] = $this->highlightResults($data['items'], $query);
        }

        $bc = $this->breadcrumb([['首页', $this->siteUrl()], ['搜索', null]]);

        $this->display('search', [
            'query'      => $query,
            'items'      => $data['items'],
            'total'      => $data['total'],
            'has_more'   => $data['has_more'],
            'next_page'  => $data['current_page'] + 1,
            'breadcrumb' => $bc,
        ]);
    }

    /**
     * Wrap matching keywords in <mark> tags for highlighting.
     */
    private function highlightResults(array $items, string $query): array
    {
        if (empty($query)) return $items;

        $keywords = preg_split('/[\s,，]+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($keywords)) return $items;

        foreach ($items as &$item) {
            $item['title_hl']   = $this->highlight($item['title'], $keywords);
            $item['excerpt_hl'] = $this->highlight($item['excerpt'] ?? '', $keywords);
        }

        return $items;
    }

    /**
     * Highlight keywords in text using case-insensitive regex.
     */
    private function highlight(string $text, array $keywords): string
    {
        // Escape HTML first, then apply highlights
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        foreach ($keywords as $kw) {
            if (mb_strlen($kw) < 1) continue;
            $escaped = preg_quote($kw, '/');
            $text = preg_replace(
                '/(' . $escaped . ')/iu',
                '<mark class="search-highlight">$1</mark>',
                $text
            );
        }

        return $text;
    }
}

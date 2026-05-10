<?php

namespace LyBlog\Plugins\Sitemap;

use LyBlog\Core\Hook;
use LyBlog\Core\Config;
use LyBlog\Core\Database;
use LyBlog\Core\Request;

class SitemapPlugin
{
    public function __construct()
    {
        Hook::addAction('before_dispatch', [$this, 'handleRequest']);
    }

    public function handleRequest(Request $request): void
    {
        if ($request->getPath() !== '/sitemap.xml') {
            return;
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo $this->generate();
        exit;
    }

    private function generate(): string
    {
        $siteUrl = rtrim(Config::get('site_url', '/'), '/');
        $db = Database::getInstance();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $xml .= $this->url($siteUrl, '1.0', 'daily');
        $xml .= $this->url($siteUrl . '/archive', '0.8', 'weekly');
        $xml .= $this->url($siteUrl . '/search', '0.3', 'monthly');

        // Articles
        if ($db) {
            $articles = $db->fetchAll(
                "SELECT slug, updated_at FROM {articles} WHERE status = 'published' AND published_at <= NOW() ORDER BY published_at DESC"
            );
            foreach ($articles as $a) {
                $xml .= $this->url(
                    $siteUrl . '/article/' . $a['slug'],
                    '0.7',
                    null,
                    date('c', strtotime($a['updated_at']))
                );
            }

            // Pages
            $pages = $db->fetchAll(
                "SELECT slug, updated_at FROM {pages} WHERE status = 'published'"
            );
            foreach ($pages as $p) {
                $xml .= $this->url(
                    $siteUrl . '/page/' . $p['slug'],
                    '0.6',
                    'monthly',
                    date('c', strtotime($p['updated_at']))
                );
            }

            // Categories
            $categories = $db->fetchAll("SELECT slug FROM {categories}");
            foreach ($categories as $c) {
                $xml .= $this->url($siteUrl . '/category/' . $c['slug'], '0.5', 'weekly');
            }

            // Tags
            $tags = $db->fetchAll("SELECT slug FROM {tags}");
            foreach ($tags as $t) {
                $xml .= $this->url($siteUrl . '/tag/' . $t['slug'], '0.4', 'weekly');
            }
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function url(string $loc, string $priority = '0.5', ?string $changefreq = null, ?string $lastmod = null): string
    {
        $xml = "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
        if ($lastmod) $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
        if ($changefreq) $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
        $xml .= "    <priority>{$priority}</priority>\n";
        $xml .= "  </url>\n";
        return $xml;
    }
}
